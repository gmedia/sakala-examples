<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Picks the queue, cache, and session backends when QUEUE_CONNECTION,
 * CACHE_STORE, or SESSION_DRIVER is not set.
 *
 * A Valkey store attached on Sakala's Valkey page sets REDIS_URL (plus
 * REDIS_HOST, REDIS_PORT, and REDIS_PASSWORD) at deploy time, but never these
 * three, because they change how the app behaves. So the app decides: with
 * REDIS_URL, everything goes to Valkey; without it, jobs wait in the `jobs`
 * table of the database, the cache lives in files, and the session in an
 * encrypted cookie. Without a database either, the queue is `sync`, so the
 * worker idles instead of failing every second.
 */
final class Backends
{
    /** Set by Sakala when a Valkey store is attached to the project. */
    public const VALKEY_VARIABLE = 'REDIS_URL';

    /** Name of the Redis queue connection, cache store, and session driver. */
    public const REDIS = 'redis';

    public const QUEUE_DATABASE = 'database';

    public const QUEUE_NONE = 'sync';

    public const CACHE_WITHOUT_VALKEY = 'file';

    public const SESSION_WITHOUT_VALKEY = 'cookie';

    /*
     * Only call these from files in config/: env() returns null once the
     * configuration is cached.
     */

    public static function valkeyConfigured(): bool
    {
        return filled(env(self::VALKEY_VARIABLE));
    }

    public static function queue(): string
    {
        if (self::valkeyConfigured()) {
            return self::REDIS;
        }

        $database = env('DB_CONNECTION', DatabaseConnection::fallback());

        return $database === DatabaseConnection::PLACEHOLDER ? self::QUEUE_NONE : self::QUEUE_DATABASE;
    }

    public static function cache(): string
    {
        return self::valkeyConfigured() ? self::REDIS : self::CACHE_WITHOUT_VALKEY;
    }

    public static function session(): string
    {
        return self::valkeyConfigured() ? self::REDIS : self::SESSION_WITHOUT_VALKEY;
    }
}
