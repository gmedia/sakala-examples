<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GuestbookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_guestbook_shows_the_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Tinggalkan pesan')
            ->assertSee('Belum ada pesan.');
    }

    public function test_a_visitor_can_write_and_read_an_entry(): void
    {
        $this->post('/entries', ['name' => 'Rina', 'message' => 'Halo dari Sakala!'])
            ->assertRedirect('/');

        $this->assertDatabaseHas('guestbook_entries', ['name' => 'Rina', 'message' => 'Halo dari Sakala!']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Halo dari Sakala!')
            ->assertSee('Rina');
    }

    public function test_entries_are_escaped(): void
    {
        GuestbookEntry::query()->create(['name' => 'Tamu', 'message' => '<script>alert(1)</script>']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;', escape: false);
    }

    public function test_invalid_entries_are_rejected_with_indonesian_messages(): void
    {
        $this->post('/entries', ['name' => '', 'message' => str_repeat('a', GuestbookEntry::MESSAGE_MAX_LENGTH + 1)])
            ->assertSessionHasErrors([
                'name' => 'Nama wajib diisi.',
                'message' => 'Pesan paling panjang '.GuestbookEntry::MESSAGE_MAX_LENGTH.' karakter.',
            ]);

        $this->assertDatabaseCount('guestbook_entries', 0);
    }

    public function test_posting_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/entries', ['name' => 'Tamu', 'message' => "Pesan {$attempt}"])->assertRedirect('/');
        }

        $this->post('/entries', ['name' => 'Tamu', 'message' => 'Satu lagi'])->assertTooManyRequests();
    }
}
