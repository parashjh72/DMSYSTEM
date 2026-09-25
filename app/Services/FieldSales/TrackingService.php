<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\LocationPing;
use App\Models\FieldSales\TrackingConsent;
use App\Models\TsoAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Duty-hours location tracking. The phone queues GPS fixes while offline and
 * uploads them in batches; a fix is stored only when the user has given
 * consent and it was taken between that day's check-in and check-out.
 */
class TrackingService
{
    /** Clock skew tolerated between the phone and the server. */
    private const CLOCK_SKEW_SECONDS = 300;

    public function __construct(private PolicyResolver $policies) {}

    public function activeConsent(User $user): ?TrackingConsent
    {
        return TrackingConsent::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('consent_version', config('field_sales.tracking.consent_version'))
            ->latest('accepted_at')
            ->first();
    }

    public function hasConsent(User $user): bool
    {
        return $this->activeConsent($user) !== null;
    }

    public function giveConsent(User $user, ?string $ip, ?string $userAgent): TrackingConsent
    {
        return $this->activeConsent($user) ?? TrackingConsent::query()->create([
            'user_id' => $user->id,
            'consent_version' => config('field_sales.tracking.consent_version'),
            'accepted_at' => now(),
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    public function revokeConsent(User $user): void
    {
        TrackingConsent::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * What the phone needs to know to run (or stop) the tracker.
     *
     * @return array{consented: bool, checked_in: bool, checked_out: bool, tracking: bool, interval_minutes: int}
     */
    public function status(User $user): array
    {
        $today = TsoAttendance::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', Carbon::now(config('field_sales.timezone'))->toDateString())
            ->first();
        $consented = $this->hasConsent($user);
        $checkedIn = $today !== null;
        $checkedOut = $today?->check_out_at !== null;

        return [
            'consented' => $consented,
            'checked_in' => $checkedIn,
            'checked_out' => $checkedOut,
            'tracking' => $consented && $checkedIn && ! $checkedOut,
            'interval_minutes' => $this->policies->forUser($user)->ping_interval_minutes,
        ];
    }

    /**
     * @param  list<array{id: string, t: int|float, lat: float, lng: float, acc?: ?float, speed?: ?float, battery?: ?int}>  $pings  t = unix milliseconds
     * @return array{accepted: int, duplicates: int, rejected: array<string, string>}
     */
    public function ingest(User $user, array $pings): array
    {
        $tz = config('field_sales.timezone');
        $now = now();
        $oldest = $now->copy()->subHours(config('field_sales.tracking.max_age_hours'));
        $newest = $now->copy()->addSeconds(self::CLOCK_SKEW_SECONDS);
        $minAccuracy = (float) config('attendance.anti_mock.min_accuracy_metres', 0.5);
        $maxSpeedKmh = (float) config('field_sales.tracking.max_speed_kmh');

        $pings = collect($pings)
            ->map(fn (array $p) => $p + ['at' => Carbon::createFromTimestampMs((int) $p['t'])->utc()])
            ->sortBy('at')
            ->values();

        $attendances = $this->attendancesFor($user, $pings->pluck('at'), $tz);
        $previous = LocationPing::query()->where('user_id', $user->id)
            ->where('recorded_at', '<', $pings->first()['at'] ?? $now)
            ->where('is_suspect', false)
            ->latest('recorded_at')
            ->first(['latitude', 'longitude', 'recorded_at']);
        $previous = $previous ? ['lat' => $previous->latitude, 'lng' => $previous->longitude, 'at' => $previous->recorded_at] : null;

        $rows = [];
        $rejected = [];

        foreach ($pings as $ping) {
            /** @var Carbon $at */
            $at = $ping['at'];
            $reason = match (true) {
                $at->gt($newest) => 'future',
                $at->lt($oldest) => 'too_old',
                ! $this->onDuty($attendances->get($at->copy()->setTimezone($tz)->toDateString()), $at) => 'off_duty',
                default => null,
            };

            if ($reason !== null) {
                $rejected[$ping['id']] = $reason;

                continue;
            }

            $accuracy = isset($ping['acc']) ? (float) $ping['acc'] : null;
            $suspect = $accuracy !== null && $accuracy < $minAccuracy;

            if ($previous !== null) {
                $metres = AttendanceService::distanceMetres($previous['lat'], $previous['lng'], (float) $ping['lat'], (float) $ping['lng']);
                $seconds = max(1, $previous['at']->diffInSeconds($at, true));
                $suspect = $suspect || ($metres > 500 && ($metres / $seconds) * 3.6 > $maxSpeedKmh);
            }

            if (! $suspect) {
                $previous = ['lat' => (float) $ping['lat'], 'lng' => (float) $ping['lng'], 'at' => $at];
            }

            $rows[] = [
                'user_id' => $user->id,
                'client_uuid' => $ping['id'],
                'recorded_at' => $at,
                'latitude' => $ping['lat'],
                'longitude' => $ping['lng'],
                'accuracy' => $accuracy,
                'speed' => isset($ping['speed']) ? (float) $ping['speed'] : null,
                'battery' => isset($ping['battery']) ? (int) $ping['battery'] : null,
                'source' => 'tracker',
                'is_suspect' => $suspect,
                'created_at' => $now,
            ];
        }

        $inserted = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            $inserted += LocationPing::query()->insertOrIgnore($chunk);
        }

        return ['accepted' => $inserted, 'duplicates' => count($rows) - $inserted, 'rejected' => $rejected];
    }

    /**
     * @param  Collection<int, Carbon>  $times
     * @return Collection<string, TsoAttendance> keyed by local date
     */
    private function attendancesFor(User $user, Collection $times, string $tz): Collection
    {
        $dates = $times->map(fn (Carbon $t) => $t->copy()->setTimezone($tz)->toDateString())->unique()->values();
        if ($dates->isEmpty()) {
            return collect();
        }

        return TsoAttendance::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', '>=', $dates->min())
            ->whereDate('attendance_date', '<=', $dates->max())
            ->get()
            ->keyBy(fn (TsoAttendance $a) => $a->attendance_date->toDateString());
    }

    private function onDuty(?TsoAttendance $attendance, Carbon $at): bool
    {
        if ($attendance?->check_in_at === null || $at->lt($attendance->check_in_at->copy()->subMinute())) {
            return false;
        }

        return $attendance->check_out_at === null || $at->lte($attendance->check_out_at->copy()->addMinute());
    }
}
