<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/** A configured database whose tables are missing, as when the release command never ran. */
class UnavailableDatabaseTest extends TestCase
{
    public function test_page_reports_that_the_database_cannot_be_read(): void
    {
        $this->get('/')
            ->assertServiceUnavailable()
            ->assertSee('Database belum bisa dibaca');
    }
}
