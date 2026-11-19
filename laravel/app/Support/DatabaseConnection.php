<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decides which database connection the app uses when DB_CONNECTION is not set.
 *
 * A database attached on Sakala's Database page sets DB_CONNECTION=pgsql,
 * DATABASE_URL, and DB_* at deploy time, so this fallback does not decide
 * anything there. It matters before a database is attached, and for an
 * external PostgreSQL given only as DATABASE_URL or DB_HOST. With no database
 * variables, the app uses a placeholder connection (in-memory SQLite):
 * `php artisan migrate --force` still exits 0, and the guestbook page explains
 * how to attach a database instead of failing.
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
