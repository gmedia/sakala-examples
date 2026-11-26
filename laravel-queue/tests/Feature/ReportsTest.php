<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Jobs\GenerateReport;
use App\Models\CleanupRun;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    public function test_empty_page_shows_the_form_and_status(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Buat laporan')
            ->assertSee('Belum ada laporan.')
            ->assertSeeInOrder(['Antrean', 'sync', 'Job terakhir', 'belum ada', 'Jadwal terakhir', 'belum pernah berjalan'])
            ->assertDontSee('http-equiv="refresh"', escape: false);
    }

    public function test_a_report_is_queued_and_not_run_by_the_web_request(): void
    {
        Queue::fake();

        $this->post('/reports', ['title' => 'Penjualan November'])
            ->assertRedirect('/');

        $report = Report::query()->sole();
        $this->assertSame(ReportStatus::Queued, $report->status);
        Queue::assertPushed(GenerateReport::class, fn (GenerateReport $job) => $job->report->is($report));

        $this->get('/')
            ->assertSee('Penjualan November')
            ->assertSee('menunggu worker')
            ->assertSee('http-equiv="refresh"', escape: false);
    }

    public function test_the_job_marks_the_report_done_with_its_queue_connection(): void
    {
        $this->post('/reports', ['title' => 'Stok gudang'])->assertRedirect('/');

        $report = Report::query()->sole();
        $this->assertSame(ReportStatus::Done, $report->status);
        $this->assertSame('sync', $report->queue_connection);
        $this->assertNotNull($report->finished_at);
        Sleep::assertSleptTimes(1);

        $this->get('/')
            ->assertSee('selesai lewat <code>sync</code>', escape: false)
            ->assertSeeInOrder(['Job terakhir', 'selesai', 'detik setelah dikirim'])
            ->assertDontSee('http-equiv="refresh"', escape: false);
    }

    public function test_titles_are_escaped(): void
    {
        Report::query()->create(['title' => '<script>alert(1)</script>']);

        $this->get('/')
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;', escape: false);
    }

    public function test_invalid_titles_are_rejected_with_indonesian_messages(): void
    {
        $this->post('/reports', ['title' => ''])->assertSessionHasErrors(['title' => 'Judul wajib diisi.']);
        $this->post('/reports', ['title' => str_repeat('a', Report::TITLE_MAX_LENGTH + 1)])
            ->assertSessionHasErrors(['title' => 'Judul paling panjang '.Report::TITLE_MAX_LENGTH.' karakter.']);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_requests_are_rate_limited(): void
    {
        Queue::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/reports', ['title' => "Laporan {$attempt}"])->assertRedirect('/');
        }

        $this->post('/reports', ['title' => 'Satu lagi'])->assertTooManyRequests();
    }

    public function test_cleanup_removes_old_reports_and_records_the_run(): void
    {
        $this->travelTo(now()->subDays(Report::KEEP_DAYS + 1));
        Report::query()->create(['title' => 'Lama']);
        $this->travelBack();
        Report::query()->create(['title' => 'Baru']);

        $this->artisan('reports:cleanup')
            ->expectsOutputToContain('Removed 1 reports')
            ->assertSuccessful();

        $this->assertSame(['Baru'], Report::query()->pluck('title')->all());
        $this->assertSame(1, CleanupRun::query()->sole()->deleted_reports);

        $this->get('/')->assertSeeInOrder(['Jadwal terakhir', 'WIB, 1 laporan lama dihapus']);
    }

    public function test_times_are_shown_in_the_display_timezone(): void
    {
        $this->travelTo('2026-11-26 07:30:00');
        CleanupRun::query()->create(['deleted_reports' => 0]);

        $this->get('/')->assertSee('26-11-2026 14.30 WIB');
    }
}
