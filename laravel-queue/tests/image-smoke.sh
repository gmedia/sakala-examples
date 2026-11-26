#!/usr/bin/env bash
# Runs the image the way Sakala does: the release command and scheduled
# commands in one-off containers, the web process and the worker as long-lived
# containers, all from one image and with Sakala's capability set. First with
# PostgreSQL and Valkey, then with PostgreSQL only.
#
# Usage: tests/image-smoke.sh [image]   (needs Docker and curl)
set -euo pipefail

IMAGE="${1:-sakala-example-laravel-queue}"
PREFIX="lq-smoke"
NETWORK="${PREFIX}-net"
PORT=8123
URL="http://127.0.0.1:${PORT}"
WORKER_COMMAND=(php artisan queue:work --sleep=1 --tries=3 --max-time=3600)
CAPS=(--cap-drop ALL --cap-add CHOWN --cap-add DAC_OVERRIDE --cap-add FOWNER --cap-add FSETID --cap-add KILL
      --cap-add SETGID --cap-add SETUID --cap-add NET_BIND_SERVICE --security-opt no-new-privileges)

random() { head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9'; }
APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
PG_PASSWORD="$(random)"
VALKEY_PASSWORD="$(random)"
JAR="$(mktemp)"

cleanup() {
  docker rm -f "${PREFIX}-web" "${PREFIX}-worker" "${PREFIX}-pg" "${PREFIX}-valkey" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -f "$JAR"
}
trap cleanup EXIT

fail() {
  echo "FAIL: $*" >&2
  docker logs --tail 40 "${PREFIX}-web" >&2 2>&1 || true
  docker logs --tail 40 "${PREFIX}-worker" >&2 2>&1 || true
  exit 1
}

# What an attached Sakala database and Valkey store inject. The Valkey URL has
# no database number, like Sakala's.
DATABASE_ENV=(-e DB_CONNECTION=pgsql -e "DATABASE_URL=postgresql://postgres:${PG_PASSWORD}@${PREFIX}-pg:5432/app")
VALKEY_ENV=(-e "REDIS_URL=redis://:${VALKEY_PASSWORD}@${PREFIX}-valkey:6379")

one_off() { docker run --rm --network "$NETWORK" "${CAPS[@]}" -e APP_KEY="$APP_KEY" "$@"; }
valkey() { docker exec "${PREFIX}-valkey" valkey-cli --no-auth-warning -a "$VALKEY_PASSWORD" "$@"; }
# Laravel's Redis key prefix (REDIS_PREFIX, from APP_NAME) plus the default queue.
QUEUE_KEY="laravel-database-queues:default"
queued_jobs() { valkey -n 0 llen "$QUEUE_KEY"; }
cache_keys() { valkey -n 1 dbsize; }

start_worker() {
  docker rm -f "${PREFIX}-worker" >/dev/null 2>&1 || true
  docker run -d --name "${PREFIX}-worker" --network "$NETWORK" "${CAPS[@]}" -e APP_KEY="$APP_KEY" "$@" "$IMAGE" "${WORKER_COMMAND[@]}" >/dev/null
}

start_web() {
  docker rm -f "${PREFIX}-web" >/dev/null 2>&1 || true
  docker run -d --name "${PREFIX}-web" --network "$NETWORK" "${CAPS[@]}" -e APP_KEY="$APP_KEY" -e PORT="$PORT" \
    -p "127.0.0.1:${PORT}:${PORT}" "$@" "$IMAGE" >/dev/null
  for _ in $(seq 1 30); do
    curl -fsS -o /dev/null "${URL}/up" && return 0
    sleep 1
  done
  fail "web did not answer /up"
}

submit_report() {
  local token
  token="$(curl -fsS -c "$JAR" -b "$JAR" "${URL}/" | sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' | head -n 1)"
  [ -n "$token" ] || fail "no CSRF token on the page"
  curl -fsS -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "_token=${token}" --data-urlencode "title=$1" "${URL}/reports"
}

expect_page() {
  for _ in $(seq 1 30); do
    curl -fsS "${URL}/" | grep -q "$1" && { echo "ok: page shows $1"; return 0; }
    sleep 1
  done
  fail "page never showed $1"
}

docker network create "$NETWORK" >/dev/null
docker run -d --name "${PREFIX}-pg" --network "$NETWORK" -e POSTGRES_PASSWORD="$PG_PASSWORD" -e POSTGRES_DB=app postgres:18-alpine >/dev/null
docker run -d --name "${PREFIX}-valkey" --network "$NETWORK" valkey/valkey:9.2.0-alpine valkey-server --requirepass "$VALKEY_PASSWORD" >/dev/null
for _ in $(seq 1 30); do
  docker exec "${PREFIX}-pg" pg_isready -U postgres -d app >/dev/null 2>&1 && break
  sleep 1
done

echo "== release command"
one_off "${DATABASE_ENV[@]}" "$IMAGE" php artisan migrate --force

echo "== with Valkey: queue, cache, and session use redis"
start_web "${DATABASE_ENV[@]}" "${VALKEY_ENV[@]}"
expect_page '<code>redis</code>'
submit_report "Laporan lewat Valkey"
[ "$(queued_jobs)" -eq 1 ] || fail "the job is not waiting in Valkey database 0"
[ "$(cache_keys)" -gt 0 ] || fail "the rate limiter left nothing in the cache, Valkey database 1"
echo "ok: before cache:clear, $(queued_jobs) job waits in database 0, the cache has $(cache_keys) keys in database 1"

echo "== cache:clear in a one-off container keeps the queued job"
one_off "${DATABASE_ENV[@]}" "${VALKEY_ENV[@]}" "$IMAGE" php artisan cache:clear
[ "$(cache_keys)" -eq 0 ] || fail "cache:clear left keys in database 1"
[ "$(queued_jobs)" -eq 1 ] || fail "cache:clear removed the queued job"
echo "ok: after cache:clear, $(queued_jobs) job still waits in database 0, the cache has $(cache_keys) keys"

echo "== worker picks the job up"
start_worker "${DATABASE_ENV[@]}" "${VALKEY_ENV[@]}"
expect_page 'selesai lewat <code>redis</code>'

echo "== scheduled command in a one-off container"
one_off "${DATABASE_ENV[@]}" "${VALKEY_ENV[@]}" "$IMAGE" php artisan reports:cleanup
expect_page 'laporan lama dihapus'

echo "== without Valkey: the queue falls back to the database"
: >"$JAR"
start_web "${DATABASE_ENV[@]}"
start_worker "${DATABASE_ENV[@]}"
expect_page '<code>database</code>'
submit_report "Laporan lewat database"
expect_page 'selesai lewat <code>database</code>'

echo "PASS"
