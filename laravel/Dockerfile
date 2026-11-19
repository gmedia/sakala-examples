# syntax=docker/dockerfile:1

# FrankenPHP on Alpine: a small image that already has PHP, pdo_sqlite, and
# install-php-extensions. Sakala uses this Dockerfile instead of Railpack
# because it is in the project's Root directory.
FROM dunglas/frankenphp:1-php8.4-alpine

# PostgreSQL driver. pdo_sqlite is already built in.
RUN install-php-extensions pdo_pgsql

# Composer, pinned and verified, without pulling a second image.
ADD --chmod=755 --checksum=sha256:dd158c0afd41d15fac89e1b4e47b5d42911fe316c3687cd5edac98319b25d028 \
    https://getcomposer.org/download/2.11.2/composer.phar /usr/local/bin/composer

WORKDIR /app

# Dependencies first, so a code change does not reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R a+rw storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Sakala sets PORT. 8080 is only the fallback for running the image yourself.
EXPOSE 8080

# Cache config and routes with the real environment variables, then serve public/.
CMD ["sh", "-c", "php artisan optimize && exec frankenphp php-server --listen \":${PORT:-8080}\" --root public"]
