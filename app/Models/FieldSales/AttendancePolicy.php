<?php

namespace App\Models\FieldSales;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Duty rules for field users. area_id = null is the company default.
 */
#[Fillable([
    'area_id', 'duty_start', 'duty_end', 'late_grace_minutes', 'half_day_below_minutes',
    'weekly_off', 'geofence_mode', 'ping_interval_minutes',
])]
class AttendancePolicy extends Model
{
    public const GEOFENCE_MODES = ['off', 'flag', 'block'];

    protected $table = 'fs_attendance_policies';

    protected function casts(): array
    {
        return [
            'late_grace_minutes' => 'integer',
            'half_day_below_minutes' => 'integer',
            'ping_interval_minutes' => 'integer',
            'weekly_off' => 'array',
        ];
    }

    /** Unsaved policy built from config('field_sales.policy_defaults'). */
    public static function fromDefaults(): self
    {
        $defaults = config('field_sales.policy_defaults');

        return new self([
            'area_id' => null,
            'duty_start' => $defaults['duty_start'],
            'duty_end' => $defaults['duty_end'],
            'late_grace_minutes' => $defaults['late_grace_minutes'],
            'half_day_below_minutes' => $defaults['half_day_below_minutes'],
            'weekly_off' => $defaults['weekly_off'],
            'geofence_mode' => $defaults['geofence_mode'],
            'ping_interval_minutes' => $defaults['ping_interval_minutes'],
        ]);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return list<int> */
    public function weeklyOffDays(): array
    {
        return array_map('intval', (array) $this->weekly_off);
    }

    /** "09:30" from either "09:30" or "09:30:00". */
    public function dutyStartTime(): string
    {
        return substr((string) $this->duty_start, 0, 5);
    }

    public function dutyEndTime(): string
    {
        return substr((string) $this->duty_end, 0, 5);
    }
}
