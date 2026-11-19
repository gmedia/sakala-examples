<?php

declare(strict_types=1);

namespace App\Support;

/** What the guestbook page tells the visitor about the database. */
enum GuestbookState
{
    /** No database variables are set; the app runs on the placeholder connection. */
    case NotConfigured;

    /** A database is configured but could not be read. */
    case Unavailable;

    case Ready;
}
