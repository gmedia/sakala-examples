<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\DatabaseConnection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Guestbook posts allowed per visitor per minute. */
    private const GUESTBOOK_POSTS_PER_MINUTE = 5;

    public function boot(): void
    {
        RateLimiter::for('guestbook', fn (Request $request) => Limit::perMinute(self::GUESTBOOK_POSTS_PER_MINUTE)->by((string) $request->ip()));

        // Explain in the deployment log why `migrate` succeeds without a database.
        Event::listen(function (CommandStarting $event): void {
            if ($event->command === 'migrate' && ! DatabaseConnection::isConfigured()) {
                $event->output->writeln('<comment>No database configured (DATABASE_URL, DB_URL, DB_HOST). Migrations run against a throwaway in-memory SQLite database and nothing is stored.</comment>');
            }
        });
    }
}
