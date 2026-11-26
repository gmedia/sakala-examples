<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Runs in the worker process (`php artisan queue:work`), not in the web
 * request. It stands in for slow work such as building a PDF: it waits a
 * moment, then marks the report done and records which queue carried it.
 */
class GenerateReport implements ShouldQueue
{
    use Queueable;

    /** Pretend work, long enough to see the report wait on the page. */
    public const WORK_SECONDS = 2;

    public function __construct(public Report $report) {}

    public function handle(): void
    {
        Sleep::for(self::WORK_SECONDS)->seconds();

        $connection = $this->job?->getConnectionName() ?? (string) config('queue.default');
        $this->report->markDone($connection);

        Log::info('Report done', ['report' => $this->report->id, 'queue' => $connection]);
    }
}
