<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DatabaseConnection;
use Tests\TestCase;

/** The example must deploy and answer before a database exists. */
class WithoutDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => DatabaseConnection::PLACEHOLDER]);
    }

    public function test_page_explains_how_to_attach_a_database(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('database belum dihubungkan')
            ->assertSeeInOrder(['halaman <strong>Database</strong>', 'Pasang ke proyek', 'Redeploy'], escape: false)
            ->assertDontSee('Buat laporan');
    }

    public function test_posting_without_a_database_redirects_back(): void
    {
        $this->post('/reports', ['title' => 'Halo'])->assertRedirect('/');
    }

    public function test_release_command_succeeds_without_a_database(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_cleanup_fails_clearly_without_a_database(): void
    {
        $this->artisan('reports:cleanup')
            ->expectsOutputToContain('No database is attached')
            ->assertFailed();
    }

    public function test_health_endpoint_answers(): void
    {
        $this->get('/up')->assertOk();
    }
}
