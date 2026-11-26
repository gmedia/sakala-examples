<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    /** Saved by the web process; the job is waiting in the queue. */
    case Queued = 'queued';

    /** The worker ran the job. */
    case Done = 'done';
}
