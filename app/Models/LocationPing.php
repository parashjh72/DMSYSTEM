<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'tso_attendance_id', 'recorded_at',
    'latitude', 'longitude', 'accuracy', 'speed', 'battery', 'segment_metres',
])]
class LocationPing extends Model
{
    use Prunable;

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'speed' => 'float',
            'battery' => 'integer',
            'segment_metres' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(TsoAttendance::class, 'tso_attendance_id');
    }

    /** An anchor ping is a point on the de-jittered route. */
    public function isAnchor(): bool
    {
        return $this->segment_metres !== null;
    }

    /** Breadcrumbs older than the retention window are removed by `model:prune`. */
    public function prunable(): Builder
    {
        return static::where('recorded_at', '<', now()->subDays((int) config('tracking.retention_days')));
    }
}
