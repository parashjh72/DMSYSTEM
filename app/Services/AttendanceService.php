<?php

namespace App\Services;

use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * @param  array{latitude: float, longitude: float, accuracy: ?float, telemetry?: array}  $gps
     */
    public function checkIn(User $user, array $gps): TsoAttendance
    {
        $this->assertGps($gps);
        $this->assertNotMockLocation($user, $gps, 'check_in');

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
     * @param  array{latitude: float, longitude: float, accuracy: ?float, telemetry?: array}  $gps
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

        $this->assertNotMockLocation($user, $gps, 'check_out', $record);

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

    /**
     * Anti-Mock Location & GPS Spoofing Verification.
     *
     * Detects when a field user uses Android Developer Options ("Select mock location app")
     * or Fake GPS tools to spoof coordinates. Real civilian smartphone GPS exhibits natural
     * satellite drift and accuracy bounds, whereas mock apps inject identical static floats.
     */
    private function assertNotMockLocation(User $user, array $gps, string $action, ?TsoAttendance $todayRecord = null): void
    {
        if (! config('attendance.anti_mock.enabled', true)) {
            return;
        }

        $currentLat = (float) $gps['latitude'];
        $currentLng = (float) $gps['longitude'];
        $accuracy = isset($gps['accuracy']) && is_numeric($gps['accuracy']) ? (float) $gps['accuracy'] : null;

        // 1. Accuracy Sanity: Standard mobile GPS cannot report accuracy <= 0.5m.
        // Mock apps frequently report 0 or exact integers like 0.0 or 1.0.
        $minAccuracy = (float) config('attendance.anti_mock.min_accuracy_metres', 0.5);
        if ($accuracy !== null && $accuracy < $minAccuracy) {
            Log::warning('Mock location detected (invalid accuracy)', [
                'user_id' => $user->id,
                'accuracy' => $accuracy,
                'coords' => [$currentLat, $currentLng],
            ]);
            throw new RuntimeException('Suspicious GPS reading (0m accuracy). Mock location apps typically report 0 accuracy. Please disable Developer Options mock location apps and use authentic satellite GPS.');
        }

        // 1b. Hand-typed pins: real GPS reports 6+ decimals; Fake GPS apps where a user
        // types coordinates usually submit short, rounded values on both axes.
        $roundDecimals = (int) config('attendance.anti_mock.round_coordinate_decimals', 4);
        if ($roundDecimals > 0 && self::isRounded($currentLat, $roundDecimals) && self::isRounded($currentLng, $roundDecimals)) {
            Log::warning('Mock location detected (rounded coordinates)', [
                'user_id' => $user->id,
                'coords' => [$currentLat, $currentLng],
            ]);
            throw new RuntimeException('Suspicious location: GPS coordinates look hand-entered. Please disable Fake GPS / mock location apps and use your device location.');
        }

        // 2. Telemetry Multi-Sample Jitter Check:
        // When client sends sequential GPS fixes, verify that physical satellite jitter occurred.
        // Fake GPS injects 100% bit-identical coordinates across fixes.
        // Only applied to fixes claiming satellite-grade accuracy: Wi-Fi/cell fixes can repeat exactly.
        $samples = self::distinctSamples($gps['telemetry']['samples'] ?? []);
        $jitterMaxAccuracy = (float) config('attendance.anti_mock.jitter_max_accuracy_metres', 25);
        $claimsSatelliteAccuracy = collect($samples)->every(
            fn (array $sample): bool => is_numeric($sample['accuracy'] ?? null) && (float) $sample['accuracy'] <= $jitterMaxAccuracy
        );
        if (count($samples) >= 3 && $claimsSatelliteAccuracy) {
            $firstLat = (float) ($samples[0]['lat'] ?? 0);
            $firstLng = (float) ($samples[0]['lng'] ?? 0);
            $hasJitter = false;

            for ($i = 1; $i < count($samples); $i++) {
                $latDiff = abs((float) ($samples[$i]['lat'] ?? 0) - $firstLat);
                $lngDiff = abs((float) ($samples[$i]['lng'] ?? 0) - $firstLng);
                if ($latDiff > 0.00000001 || $lngDiff > 0.00000001) {
                    $hasJitter = true;
                    break;
                }
            }

            if (! $hasJitter) {
                Log::warning('Mock location detected (zero telemetry jitter)', [
                    'user_id' => $user->id,
                    'coords' => [$currentLat, $currentLng],
                    'samples_count' => count($samples),
                ]);

                throw new RuntimeException('Developer Mock Location detected: Zero GPS satellite jitter across consecutive fixes. Mock location apps inject static coordinates. Please disable "Select mock location app" in Android Developer Options.');
            }
        }

        // 3. Historical Repetition Check (Targeting developer mock location pinned coordinates):
        // Real satellite GPS drifts 2-15m across different days even at the exact same physical desk.
        // If coordinates match a past attendance within the repetition threshold, it indicates
        // a saved pin injected by a mock provider.
        $historyDays = (int) config('attendance.anti_mock.history_days', 30);
        $repetitionThreshold = (float) config('attendance.anti_mock.historical_repetition_threshold_metres', 1.5);
        $todayDate = Carbon::now(config('attendance.timezone'))->toDateString();
        $historyStartDate = Carbon::now(config('attendance.timezone'))->subDays($historyDays)->toDateString();

        $pastAttendances = TsoAttendance::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', '<', $todayDate)
            ->whereDate('attendance_date', '>=', $historyStartDate)
            ->where(function ($q) {
                $q->whereNotNull('check_in_latitude')
                    ->orWhereNotNull('check_out_latitude');
            })
            ->get(['id', 'attendance_date', 'check_in_latitude', 'check_in_longitude', 'check_out_latitude', 'check_out_longitude']);

        foreach ($pastAttendances as $past) {
            $coordsToCheck = [];
            if ($past->check_in_latitude !== null && $past->check_in_longitude !== null) {
                $coordsToCheck[] = [(float) $past->check_in_latitude, (float) $past->check_in_longitude, 'check-in'];
            }
            if ($past->check_out_latitude !== null && $past->check_out_longitude !== null) {
                $coordsToCheck[] = [(float) $past->check_out_latitude, (float) $past->check_out_longitude, 'check-out'];
            }

            foreach ($coordsToCheck as [$prevLat, $prevLng, $type]) {
                $dist = self::distanceMetres($currentLat, $currentLng, $prevLat, $prevLng);

                if ($dist < $repetitionThreshold) {
                    Log::warning('Mock location detected (historical identical pin)', [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'current_coords' => [$currentLat, $currentLng],
                        'matched_past_id' => $past->id,
                        'matched_past_date' => $past->attendance_date?->toDateString(),
                        'matched_past_type' => $type,
                        'distance_metres' => round($dist, 3),
                    ]);

                    throw new RuntimeException(
                        'Developer Mock Location detected: Exact GPS coordinates match your previous attendance on '
                        .($past->attendance_date?->format('d M Y') ?? 'an earlier day')
                        .' (difference: '.round($dist, 2).'m). Please turn off Mock Location apps in Android Developer Settings and use authentic device GPS.'
                    );
                }
            }
        }

        // 4. Same-Day Cross-User Collision Check:
        // Detects when multiple employees share the same spoofed coordinates on the same date.
        $crossUserThreshold = (float) config('attendance.anti_mock.cross_user_collision_metres', 1.0);
        $collidingRecord = TsoAttendance::query()
            ->whereDate('attendance_date', $todayDate)
            ->where('user_id', '!=', $user->id)
            ->whereNotNull('check_in_latitude')
            ->whereNotNull('check_in_longitude')
            ->get(['id', 'user_id', 'check_in_latitude', 'check_in_longitude'])
            ->first(function ($rec) use ($currentLat, $currentLng, $crossUserThreshold) {
                return self::distanceMetres($currentLat, $currentLng, (float) $rec->check_in_latitude, (float) $rec->check_in_longitude) < $crossUserThreshold;
            });

        if ($collidingRecord) {
            Log::warning('Mock location detected (cross-user collision)', [
                'user_id' => $user->id,
                'colliding_user_id' => $collidingRecord->user_id,
                'coords' => [$currentLat, $currentLng],
            ]);

            throw new RuntimeException('Suspicious location: Identical GPS coordinates match another employee today. Shared mock location pins are not permitted.');
        }

        // 5. Same-Day Check-In vs Check-Out Repetition Check:
        // If a user checked in with a mock location and checks out with the same mock location,
        // the coordinates will be identical down to sub-meters.
        if ($action === 'check_out' && $todayRecord && $todayRecord->check_in_latitude && $todayRecord->check_in_longitude) {
            $inLat = (float) $todayRecord->check_in_latitude;
            $inLng = (float) $todayRecord->check_in_longitude;
            $checkoutThreshold = (float) config('attendance.anti_mock.checkout_repetition_threshold_metres', 1.0);
            $distFromCheckIn = self::distanceMetres($currentLat, $currentLng, $inLat, $inLng);

            if ($distFromCheckIn < $checkoutThreshold) {
                Log::warning('Mock location detected (identical check-in and check-out)', [
                    'user_id' => $user->id,
                    'distance_metres' => round($distFromCheckIn, 3),
                ]);

                throw new RuntimeException('Developer Mock Location detected: Check-out coordinates are identical to check-in coordinates. Please disable mock location apps in Developer Options.');
            }
        }

        // 6. Impossible Travel Check:
        // Fake GPS users teleport between pins; flag a jump faster than any road trip allows.
        $this->assertPlausibleTravel($user, $currentLat, $currentLng, $todayRecord);
    }

    /**
     * Rejects a punch whose distance from the user's previous punch implies an impossible speed.
     */
    private function assertPlausibleTravel(User $user, float $currentLat, float $currentLng, ?TsoAttendance $todayRecord): void
    {
        $maxSpeedKmh = (float) config('attendance.anti_mock.max_travel_speed_kmh', 250);
        $minDistance = (float) config('attendance.anti_mock.travel_min_distance_metres', 5000);
        if ($maxSpeedKmh <= 0) {
            return;
        }

        $previous = $todayRecord
            ? [$todayRecord->check_in_latitude, $todayRecord->check_in_longitude, $todayRecord->check_in_at]
            : $this->lastPunchBefore($user, $this->today());

        [$prevLat, $prevLng, $prevAt] = $previous ?? [null, null, null];
        if ($prevLat === null || $prevLng === null || $prevAt === null) {
            return;
        }

        $distance = self::distanceMetres($currentLat, $currentLng, (float) $prevLat, (float) $prevLng);
        if ($distance < $minDistance) {
            return;
        }

        $hours = max(1, $prevAt->diffInSeconds(now(), true)) / 3600;
        $speedKmh = ($distance / 1000) / $hours;

        if ($speedKmh > $maxSpeedKmh) {
            Log::warning('Mock location detected (impossible travel)', [
                'user_id' => $user->id,
                'coords' => [$currentLat, $currentLng],
                'previous_coords' => [(float) $prevLat, (float) $prevLng],
                'distance_km' => round($distance / 1000, 1),
                'speed_kmh' => round($speedKmh),
            ]);

            throw new RuntimeException(
                'Suspicious location: you are '.round($distance / 1000, 1).' km from your last punch '
                .$prevAt->diffForHumans().'. That distance cannot be travelled so quickly. Please disable Fake GPS / mock location apps.'
            );
        }
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?Carbon}|null
     */
    private function lastPunchBefore(User $user, Carbon $day): ?array
    {
        $last = TsoAttendance::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', '<', $day)
            ->whereNotNull('check_in_latitude')
            ->orderByDesc('attendance_date')
            ->first();

        if (! $last) {
            return null;
        }

        return $last->check_out_at && $last->check_out_latitude !== null
            ? [$last->check_out_latitude, $last->check_out_longitude, $last->check_out_at]
            : [$last->check_in_latitude, $last->check_in_longitude, $last->check_in_at];
    }

    /**
     * Drops repeated deliveries of the same cached fix (same timestamp) so they are not mistaken for zero jitter.
     *
     * @param  array<int, mixed>  $samples
     * @return array<int, array{lat?: mixed, lng?: mixed, accuracy?: mixed, t?: mixed}>
     */
    private static function distinctSamples(mixed $samples): array
    {
        if (! is_array($samples)) {
            return [];
        }

        $distinct = [];
        foreach ($samples as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $key = isset($sample['t']) ? (string) $sample['t'] : 'i'.count($distinct);
            $distinct[$key] ??= $sample;
        }

        return array_values($distinct);
    }

    private static function isRounded(float $value, int $decimals): bool
    {
        return abs($value - round($value, $decimals)) < 1e-9;
    }

    /**
     * Calculates the great-circle distance between two points on the Earth's surface in metres (Haversine formula).
     */
    public static function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
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
