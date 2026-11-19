<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decides which database connection the app uses when DB_CONNECTION is not set.
 *
 * Sakala does not offer managed databases yet, so this example must deploy
 * without one. With no database variables, the app uses a placeholder
 * connection (in-memory SQLite): `php artisan migrate --force` still exits 0,
 * and the guestbook page explains how to connect PostgreSQL instead of failing.
 */
final class DatabaseConnection
{
    /** Name of the throwaway connection in config/database.php. */
    public const PLACEHOLDER = 'placeholder';

    /** Connection used when one of the variables below points at a database. */
    public const CONFIGURED_DEFAULT = 'pgsql';

    /** Any of these means a real database was configured. */
    private const DATABASE_VARIABLES = ['DATABASE_URL', 'DB_URL', 'DB_HOST'];

    /**
     * Only call this from config/database.php: env() returns null once the
     * configuration is cached.
     */
    public static function fallback(): string
    {
        foreach (self::DATABASE_VARIABLES as $variable) {
            if (filled(env($variable))) {
                return self::CONFIGURED_DEFAULT;
            }
        }

        return self::PLACEHOLDER;
    }

    public static function isConfigured(): bool
    {
        return config('database.default') !== self::PLACEHOLDER;
    }
}
