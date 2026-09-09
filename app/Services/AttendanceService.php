<?php

namespace App\Services;

use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * GPS check-in / check-out for field users. One record per user per working day
 * (enforced by a unique index and re-checked here). Coordinates always come from
 * the browser Geolocation API — never user input.
 */
class AttendanceService
{
    public function today(): Carbon
    {
        return Carbon::now(config('attendance.timezone'))->startOfDay();
    }

    public function todayFor(User $user): ?TsoAttendance
    {
        return TsoAttendance::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $this->today())
            ->first();
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy: ?float}  $gps
     */
    public function checkIn(User $user, array $gps): TsoAttendance
    {
        $this->assertGps($gps);

        if ($this->todayFor($user)) {
            throw new RuntimeException('You have already checked in today.');
        }

        return TsoAttendance::create([
            'user_id' => $user->id,
            'attendance_date' => Carbon::now(config('attendance.timezone'))->toDateString(),
            'check_in_at' => now(),
            'check_in_latitude' => $gps['latitude'],
            'check_in_longitude' => $gps['longitude'],
            'check_in_accuracy' => $gps['accuracy'] ?? null,
            'check_in_address' => $this->address($gps['latitude'], $gps['longitude']),
            'status' => 'checked_in',
        ]);
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy: ?float}  $gps
     */
    public function checkOut(User $user, array $gps): TsoAttendance
    {
        $this->assertGps($gps);

        $record = $this->todayFor($user);
        if (! $record) {
            throw new RuntimeException('You have not checked in today.');
        }
        if ($record->isCheckedOut()) {
            throw new RuntimeException('You have already checked out today.');
        }

        $now = now();

        $record->update([
            'check_out_at' => $now,
            'check_out_latitude' => $gps['latitude'],
            'check_out_longitude' => $gps['longitude'],
            'check_out_accuracy' => $gps['accuracy'] ?? null,
            'check_out_address' => $this->address($gps['latitude'], $gps['longitude']),
            'working_minutes' => max(0, $record->check_in_at->diffInMinutes($now)),
            'status' => 'checked_out',
        ]);

        return $record->refresh();
    }

    /** @param array{latitude: float, longitude: float, accuracy: ?float} $gps */
    private function assertGps(array $gps): void
    {
        if (! is_numeric($gps['latitude'] ?? null) || ! is_numeric($gps['longitude'] ?? null)
            || abs((float) $gps['latitude']) > 90 || abs((float) $gps['longitude']) > 180) {
            throw new RuntimeException('Could not read your location — please try again.');
        }
    }

    /** Best-effort reverse geocode; returns null on any failure. */
    private function address(float $lat, float $lng): ?string
    {
        if (! config('attendance.reverse_geocode')) {
            return null;
        }

        $key = 'geocode:'.round($lat, 4).','.round($lng, 4);

        return Cache::remember($key, now()->addDays(30), function () use ($lat, $lng) {
            try {
                $res = Http::timeout(4)
                    ->withHeaders(['User-Agent' => 'DMS/1.0 (attendance)'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'lat' => $lat, 'lon' => $lng, 'format' => 'json', 'zoom' => 16,
                    ]);

                return $res->ok() ? mb_substr((string) $res->json('display_name'), 0, 255) ?: null : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
