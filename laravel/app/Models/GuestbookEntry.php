<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GuestbookEntry extends Model
{
    /** Matches the column length in the create_guestbook_entries_table migration. */
    public const NAME_MAX_LENGTH = 60;

    /** Matches the column length in the create_guestbook_entries_table migration. */
    public const MESSAGE_MAX_LENGTH = 280;

    /** How many entries the page shows. */
    public const RECENT_LIMIT = 20;

    protected $fillable = ['name', 'message'];

    /** @param  Builder<self>  $query */
    public function scopeRecent(Builder $query): void
    {
        $query->latest('id')->limit(self::RECENT_LIMIT);
    }
}
