<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\Geofence;
use App\Models\FieldSales\LocationPing;
use App\Models\TsoAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Called by AttendanceService after a successful check-in / check-out: stores
 * the geofence result on the user's attendance day, refreshes the day status
 * and adds the punch location to the day's route.
 *
 * Failures are reported, never thrown: Field Sales bookkeeping must not stop
 * anyone from punching attendance.
 */
class PunchRecorder
{
    public function __construct(private AttendanceDayBuilder $days) {}

    /**
     * @param  'check_in'|'check_out'  $type
     * @param  array{status: string, geofence: ?Geofence, distance: ?int}|null  $geofence
     */
    public function record(TsoAttendance $attendance, string $type, ?array $geofence): void
    {
        rescue(fn () => $this->store($attendance, $type, $geofence));
    }

    /**
     * @param  'check_in'|'check_out'  $type
     * @param  array{status: string, geofence: ?Geofence, distance: ?int}|null  $geofence
     */
    private function store(TsoAttendance $attendance, string $type, ?array $geofence): void
    {
        $date = $attendance->attendance_date->toDateString();
        $now = now();

        AttendanceDay::query()->upsert([[
            'user_id' => $attendance->user_id,
            'attendance_date' => $date,
            'tso_attendance_id' => $attendance->id,
            "{$type}_geofence" => $geofence['status'] ?? null,
            "{$type}_geofence_id" => $geofence['geofence']?->id ?? null,
            "{$type}_distance_metres" => $geofence['distance'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['user_id', 'attendance_date'], [
            'tso_attendance_id', "{$type}_geofence", "{$type}_geofence_id", "{$type}_distance_metres", 'updated_at',
        ]);

        $this->days->build([$attendance->user], Carbon::parse($date), Carbon::parse($date));

        LocationPing::query()->create([
            'user_id' => $attendance->user_id,
            'client_uuid' => (string) Str::uuid(),
            'recorded_at' => $attendance->{"{$type}_at"},
            'latitude' => $attendance->{"{$type}_latitude"},
            'longitude' => $attendance->{"{$type}_longitude"},
            'accuracy' => $attendance->{"{$type}_accuracy"},
            'source' => $type,
            'created_at' => $now,
        ]);
    }
}
