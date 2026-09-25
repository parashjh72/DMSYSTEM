<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\DailyRoute;
use App\Models\FieldSales\LocationPing;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;

/**
 * Turns a day's GPS pings into a travel summary: distance, idle time and a
 * simplified path for playback. Inaccurate and suspect fixes are ignored, and
 * movement below the jitter threshold does not count as travel.
 */
class RouteSummarizer
{
    /** Points kept in the stored path; longer days are evenly thinned. */
    private const MAX_PATH_POINTS = 1500;

    public function summarise(User $user, Carbon $date): DailyRoute
    {
        $tz = config('field_sales.timezone');
        $start = Carbon::parse($date->toDateString(), $tz)->startOfDay();
        $maxAccuracy = (float) config('field_sales.tracking.max_accuracy_metres');
        $jitter = (float) config('field_sales.tracking.jitter_metres');
        $idleAfter = (int) config('field_sales.tracking.idle_after_minutes') * 60;

        $pings = LocationPing::query()
            ->where('user_id', $user->id)
            ->whereBetween('recorded_at', [$start->copy()->utc(), $start->copy()->endOfDay()->utc()])
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'accuracy', 'is_suspect', 'recorded_at']);

        $metres = 0.0;
        $idleSeconds = 0;
        $path = [];
        $anchor = null;       // last point we counted as "moved to"
        $arrivedAt = null;    // when we reached the anchor
        $lastSeen = null;     // latest kept fix while at the anchor
        $kept = 0;

        foreach ($pings as $ping) {
            if ($ping->is_suspect || ($ping->accuracy !== null && $ping->accuracy > $maxAccuracy)) {
                continue;
            }
            $kept++;

            if ($anchor === null) {
                $anchor = $ping;
                $arrivedAt = $lastSeen = $ping->recorded_at;
                $path[] = $this->point($ping);

                continue;
            }

            $step = AttendanceService::distanceMetres($anchor->latitude, $anchor->longitude, $ping->latitude, $ping->longitude);
            if ($step < $jitter) {
                $lastSeen = $ping->recorded_at;

                continue;
            }

            $stayed = $arrivedAt->diffInSeconds($lastSeen, true);
            $idleSeconds += $stayed >= $idleAfter ? $stayed : 0;

            $metres += $step;
            $anchor = $ping;
            $arrivedAt = $lastSeen = $ping->recorded_at;
            $path[] = $this->point($ping);
        }

        if ($anchor !== null) {
            $stayed = $arrivedAt->diffInSeconds($lastSeen, true);
            $idleSeconds += $stayed >= $idleAfter ? $stayed : 0;
        }

        $now = now();
        DailyRoute::query()->upsert([[
            'user_id' => $user->id,
            'route_date' => $start->toDateString(),
            'distance_km' => round($metres / 1000, 2),
            'ping_count' => $pings->count(),
            'kept_count' => $kept,
            'idle_minutes' => intdiv($idleSeconds, 60),
            'first_ping_at' => $pings->first()?->recorded_at,
            'last_ping_at' => $pings->last()?->recorded_at,
            'path' => json_encode($this->thin($path)),
            'computed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['user_id', 'route_date'], [
            'distance_km', 'ping_count', 'kept_count', 'idle_minutes',
            'first_ping_at', 'last_ping_at', 'path', 'computed_at', 'updated_at',
        ]);

        return DailyRoute::query()
            ->where('user_id', $user->id)
            ->whereDate('route_date', $start->toDateString())
            ->sole();
    }

    /** @return array{0: float, 1: float, 2: int} */
    private function point(LocationPing $ping): array
    {
        return [round($ping->latitude, 6), round($ping->longitude, 6), $ping->recorded_at->getTimestamp()];
    }

    /**
     * @param  list<array{0: float, 1: float, 2: int}>  $path
     * @return list<array{0: float, 1: float, 2: int}>
     */
    private function thin(array $path): array
    {
        $count = count($path);
        if ($count <= self::MAX_PATH_POINTS) {
            return $path;
        }

        $step = $count / self::MAX_PATH_POINTS;
        $thinned = [];
        for ($i = 0; $i < self::MAX_PATH_POINTS; $i++) {
            $thinned[] = $path[(int) floor($i * $step)];
        }
        $thinned[] = $path[$count - 1];

        return $thinned;
    }
}
