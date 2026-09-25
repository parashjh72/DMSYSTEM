<?php

namespace App\Models\FieldSales;

use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'attendance_date', 'tso_attendance_id', 'leave_request_id', 'status',
    'late_minutes', 'working_minutes', 'missed_checkout',
    'check_in_geofence', 'check_in_geofence_id', 'check_in_distance_metres',
    'check_out_geofence', 'check_out_geofence_id', 'check_out_distance_metres',
    'computed_at',
])]
class AttendanceDay extends Model
{
    /** Status => [short code, label] for the monthly grid. */
    public const STATUSES = [
        'present' => ['P', 'Present'],
        'late' => ['L', 'Late'],
        'half_day' => ['H', 'Half day'],
        'absent' => ['A', 'Absent'],
        'leave' => ['LV', 'Leave'],
        'weekly_off' => ['WO', 'Weekly off'],
        'holiday' => ['HO', 'Holiday'],
    ];

    protected $table = 'fs_attendance_days';

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'late_minutes' => 'integer',
            'working_minutes' => 'integer',
            'missed_checkout' => 'boolean',
            'computed_at' => 'datetime',
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

    public function checkInGeofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class, 'check_in_geofence_id');
    }
}
