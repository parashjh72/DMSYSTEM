<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\AttendancePolicy;
use App\Models\FieldSales\Holiday;
use App\Models\FieldSales\LeaveRequest;
use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out each field user's daily status (present / late / half day /
 * absent / leave / weekly off / holiday) from tso_attendances, approved leave,
 * holidays and their duty policy, and stores it in fs_attendance_days.
 *
 * Today is left without a status until the user punches (absence can only be
 * decided once the day is over); future days are never written.
 */
class AttendanceDayBuilder
{
    public function __construct(private PolicyResolver $policies, private FieldStaff $staff) {}

    /**
     * @param  iterable<User>  $users
     * @return int rows written
     */
    public function build(iterable $users, Carbon $from, Carbon $to): int
    {
        $tz = config('field_sales.timezone');
        $today = Carbon::now($tz)->startOfDay();
        $from = Carbon::parse($from->toDateString(), $tz);
        $to = Carbon::parse(min($to->toDateString(), $today->toDateString()), $tz);
        $users = Collection::make($users);

        if ($users->isEmpty() || $from->gt($to)) {
            return 0;
        }

        $userIds = $users->pluck('id')->all();

        $attendances = TsoAttendance::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->get()
            ->keyBy(fn (TsoAttendance $a) => $a->user_id.'|'.$a->attendance_date->toDateString());

        $leaves = LeaveRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString())
            ->get()
            ->groupBy('user_id');

        $holidays = Holiday::query()
            ->whereDate('holiday_date', '>=', $from->toDateString())
            ->whereDate('holiday_date', '<=', $to->toDateString())
            ->get();

        $rows = [];
        $now = now();

        foreach ($users as $user) {
            $policy = $this->policies->forUser($user);
            $regionId = $this->staff->areaFor($user)?->region_id;

            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $date = $day->toDateString();
                $attendance = $attendances->get($user->id.'|'.$date);
                $leave = $leaves->get($user->id, collect())
                    ->first(fn (LeaveRequest $l) => $l->from_date->toDateString() <= $date && $l->to_date->toDateString() >= $date);
                $isHoliday = $holidays->contains(fn (Holiday $h) => $h->holiday_date->toDateString() === $date
                    && ($h->region_id === null || $h->region_id === $regionId));

                $row = $this->resolve($policy, $day, $day->lt($today), $attendance, $leave, $isHoliday);
                if ($row === null) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $user->id,
                    'attendance_date' => $date,
                    'computed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ] + $row;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AttendanceDay::query()->upsert($chunk, ['user_id', 'attendance_date'], [
                'tso_attendance_id', 'leave_request_id', 'status', 'late_minutes',
                'working_minutes', 'missed_checkout', 'computed_at', 'updated_at',
            ]);
        }

        return count($rows);
    }

    /**
     * @return array{tso_attendance_id: ?int, leave_request_id: ?int, status: ?string, late_minutes: int, working_minutes: ?int, missed_checkout: bool}|null
     */
    private function resolve(AttendancePolicy $policy, Carbon $day, bool $isPast, ?TsoAttendance $attendance, ?LeaveRequest $leave, bool $isHoliday): ?array
    {
        $row = [
            'tso_attendance_id' => $attendance?->id,
            'leave_request_id' => $leave?->id,
            'status' => null,
            'late_minutes' => 0,
            'working_minutes' => $attendance?->working_minutes,
            'missed_checkout' => false,
        ];

        if ($attendance?->check_in_at !== null) {
            $tz = config('field_sales.timezone');
            $dutyStart = Carbon::parse($day->toDateString().' '.$policy->dutyStartTime(), $tz);
            $checkIn = $attendance->check_in_at->copy()->setTimezone($tz);
            $minutesLate = (int) max(0, $dutyStart->diffInMinutes($checkIn, false));

            $row['late_minutes'] = $minutesLate > $policy->late_grace_minutes ? $minutesLate : 0;
            $row['missed_checkout'] = $attendance->check_out_at === null && $isPast;

            if ($attendance->check_out_at !== null && (int) $attendance->working_minutes < $policy->half_day_below_minutes) {
                $row['status'] = 'half_day';
            } else {
                $row['status'] = $row['late_minutes'] > 0 ? 'late' : 'present';
            }

            return $row;
        }

        if ($leave !== null) {
            return ['status' => 'leave'] + $row;
        }

        if ($isHoliday) {
            return ['status' => 'holiday'] + $row;
        }

        if (in_array($day->dayOfWeek, $policy->weeklyOffDays(), true)) {
            return ['status' => 'weekly_off'] + $row;
        }

        return $isPast ? ['status' => 'absent'] + $row : null;
    }
}
