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

    public function test_page_explains_how_to_connect_a_database(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('database belum dihubungkan')
            ->assertSee('DATABASE_URL')
            ->assertDontSee('Tinggalkan pesan');
    }

    public function test_posting_without_a_database_redirects_back(): void
    {
        $this->post('/entries', ['name' => 'Tamu', 'message' => 'Halo'])
            ->assertRedirect('/');
    }

    public function test_release_command_succeeds_without_a_database(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_health_endpoint_answers(): void
    {
        $this->get('/up')->assertOk();
    }
}
