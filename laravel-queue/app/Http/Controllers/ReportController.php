<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreReportRequest;
use App\Jobs\GenerateReport;
use App\Models\CleanupRun;
use App\Models\Report;
use App\Support\DatabaseConnection;
use App\Support\DatabaseState;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ReportController extends Controller
{
    public function index(): Response
    {
        if (! DatabaseConnection::isConfigured()) {
            return $this->page(DatabaseState::NotConfigured);
        }

        try {
            $reports = Report::query()->recent()->get();
            $lastFinished = Report::lastFinished();
            $lastCleanup = CleanupRun::latestRun();
        } catch (QueryException $exception) {
            // Wrong credentials, an unreachable host, or missing tables because
            // the release command did not run. The details go to the log only.
            report($exception);

            return $this->page(DatabaseState::Unavailable, status: HttpStatus::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->page(DatabaseState::Ready, [
            'reports' => $reports->all(),
            'lastFinished' => $lastFinished,
            'lastCleanup' => $lastCleanup,
            'waiting' => $reports->contains('status', ReportStatus::Queued),
        ]);
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        if (! DatabaseConnection::isConfigured()) {
            return redirect()->route('reports.index');
        }

        $report = Report::query()->create($request->validated());
        GenerateReport::dispatch($report);

        return redirect()->route('reports.index')->with('queued', true);
    }

    /** @param  array<string, mixed>  $data */
    private function page(DatabaseState $state, array $data = [], int $status = HttpStatus::HTTP_OK): Response
    {
        return response()->view('reports', [
            'state' => $state,
            'queueConnection' => (string) config('queue.default'),
            'timezone' => (string) config('app.display_timezone'),
            'titleMaxLength' => Report::TITLE_MAX_LENGTH,
            'keepDays' => Report::KEEP_DAYS,
            'reports' => [],
            'lastFinished' => null,
            'lastCleanup' => null,
            'waiting' => false,
            ...$data,
        ], $status);
    }
}
