<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A report a visitor asked for. The web process saves it as `queued`, and the
 * GenerateReport job, run by the worker, marks it `done`.
 *
 * @property int $id
 * @property string $title
 * @property ReportStatus $status
 * @property string|null $queue_connection
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Report extends Model
{
    use MassPrunable;

    /** Matches the column length in the create_reports_table migration. */
    public const TITLE_MAX_LENGTH = 80;

    /** How many reports the page shows. */
    public const RECENT_LIMIT = 10;

    /** Reports older than this are removed by `php artisan reports:cleanup`. */
    public const KEEP_DAYS = 7;

    protected $fillable = ['title'];

    protected $attributes = ['status' => ReportStatus::Queued->value];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'finished_at' => 'datetime',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /** @param  Builder<self>  $query */
    public function scopeRecent(Builder $query): void
    {
        $query->latest('id')->limit(self::RECENT_LIMIT);
    }

    public static function lastFinished(): ?self
    {
        return static::query()->where('status', ReportStatus::Done)->latest('finished_at')->first();
    }

    public function markDone(string $queueConnection): void
    {
        $this->forceFill([
            'status' => ReportStatus::Done,
            'queue_connection' => $queueConnection,
            'finished_at' => now(),
        ])->save();
    }

    /** Seconds from the visitor's click to the end of the job. */
    public function waitSeconds(): ?int
    {
        return $this->finished_at === null ? null : (int) $this->created_at->diffInSeconds($this->finished_at);
    }
}
