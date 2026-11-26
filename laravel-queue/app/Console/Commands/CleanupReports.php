<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CleanupRun;
use App\Models\Report;
use App\Support\DatabaseConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The command a Sakala schedule runs in a one-off container from the live
 * image: it removes old reports and records the run so the page can show it.
 */
#[Signature('reports:cleanup')]
#[Description('Remove reports older than '.Report::KEEP_DAYS.' days and record the run')]
class CleanupReports extends Command
{
    public function handle(): int
    {
        if (! DatabaseConnection::isConfigured()) {
            $this->error('No database is attached, so there is nothing to clean up.');

            return self::FAILURE;
        }

        $deleted = (new Report)->pruneAll();
        CleanupRun::query()->create(['deleted_reports' => $deleted]);

        $this->info("Removed {$deleted} reports older than ".Report::KEEP_DAYS.' days.');

        return self::SUCCESS;
    }
}
