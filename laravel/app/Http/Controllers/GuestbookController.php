<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuestbookEntryRequest;
use App\Models\GuestbookEntry;
use App\Support\DatabaseConnection;
use App\Support\GuestbookState;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class GuestbookController extends Controller
{
    public function index(): Response
    {
        if (! DatabaseConnection::isConfigured()) {
            return $this->page(GuestbookState::NotConfigured);
        }

        try {
            $entries = GuestbookEntry::query()->recent()->get();
        } catch (QueryException $exception) {
            // Wrong credentials, an unreachable host, or a missing table because
            // the release command did not run. The details go to the log only.
            report($exception);

            return $this->page(GuestbookState::Unavailable, status: HttpStatus::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->page(GuestbookState::Ready, $entries->all());
    }

    public function store(StoreGuestbookEntryRequest $request): RedirectResponse
    {
        if (! DatabaseConnection::isConfigured()) {
            return redirect()->route('guestbook.index');
        }

        GuestbookEntry::query()->create($request->validated());

        return redirect()->route('guestbook.index')->with('saved', true);
    }

    /** @param  list<GuestbookEntry>  $entries */
    private function page(GuestbookState $state, array $entries = [], int $status = HttpStatus::HTTP_OK): Response
    {
        return response()->view('guestbook', [
            'state' => $state,
            'entries' => $entries,
            'nameMaxLength' => GuestbookEntry::NAME_MAX_LENGTH,
            'messageMaxLength' => GuestbookEntry::MESSAGE_MAX_LENGTH,
        ], $status);
    }
}
