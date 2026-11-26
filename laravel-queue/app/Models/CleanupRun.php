<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One run of `php artisan reports:cleanup`, the command the Sakala schedule
 * starts. The page shows the latest one.
 *
 * @property int $id
 * @property int $deleted_reports
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CleanupRun extends Model
{
    protected $fillable = ['deleted_reports'];

    protected function casts(): array
    {
        return ['deleted_reports' => 'integer'];
    }

    public static function latestRun(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
