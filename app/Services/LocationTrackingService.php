<?php

namespace App\Services;

use App\Models\LocationPing;
use App\Models\PjpVisit;
use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Field-force GPS tracking: stores the breadcrumbs a checked-in user's browser
 * sends, and turns a day's breadcrumbs into a route, distance travelled, stops
 * and an activity timeline (check-in, retailer visits, stops, check-out).
 */
class LocationTrackingService
{
    public function __construct(private AttendanceService $attendance) {}

    /** Today's attendance for the user while they are still checked in. */
    public function openAttendanceFor(User $user): ?TsoAttendance
    {
        $record = $this->attendance->todayFor($user);

        return $record && ! $record->isCheckedOut() ? $record : null;
    }

    /**
     * Stores a batch of breadcrumbs against an attendance day and returns how
     * many were kept. Fixes outside the working window, with poor accuracy, or
     * implying an impossible speed are dropped.
     *
     * @param  array<int, array{lat: float|string, lng: float|string, accuracy?: float|string|null, speed?: float|string|null, battery?: int|string|null, t?: int|string|null}>  $pings
     */
    public function record(TsoAttendance $attendance, array $pings): int
    {
        $windowStart = $attendance->check_in_at->copy()->subMinutes(2);
        $windowEnd = ($attendance->check_out_at ?? now())->copy()->addMinute();
        $maxAccuracy = (float) config('tracking.max_accuracy_metres');

        $normalised = collect($pings)
            ->map(fn (array $ping): array => [
                'lat' => (float) $ping['lat'],
                'lng' => (float) $ping['lng'],
                'accuracy' => isset($ping['accuracy']) ? (float) $ping['accuracy'] : null,
                'speed' => isset($ping['speed']) ? max(0.0, (float) $ping['speed']) : null,
                'battery' => isset($ping['battery']) ? (int) $ping['battery'] : null,
                'at' => isset($ping['t']) ? Carbon::createFromTimestampMs((int) $ping['t']) : now(),
            ])
            ->filter(fn (array $ping): bool => $ping['at']->betweenIncluded($windowStart, $windowEnd)
                && ($ping['accuracy'] === null || $ping['accuracy'] <= $maxAccuracy)
                && abs($ping['lat']) <= 90 && abs($ping['lng']) <= 180
                && ! ($ping['lat'] === 0.0 && $ping['lng'] === 0.0))
            ->sortBy(fn (array $ping): int => $ping['at']->getTimestampMs())
            ->values();

        if ($normalised->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($attendance, $normalised): int {
            $anchor = $this->lastAnchor($attendance);
            $kept = 0;

            foreach ($normalised as $ping) {
                $segment = null;

                if ($anchor === null) {
                    $segment = 0.0;
                } elseif ($ping['at']->greaterThanOrEqualTo($anchor['at'])) {
                    $distance = AttendanceService::distanceMetres($anchor['lat'], $anchor['lng'], $ping['lat'], $ping['lng']);

                    if ($distance >= (float) config('tracking.anchor_metres')) {
                        if ($this->isImpossibleJump($distance, $anchor['at'], $ping['at'])) {
                            continue;
                        }
                        $segment = $distance;
                    }
                }

                LocationPing::create([
                    'user_id' => $attendance->user_id,
                    'tso_attendance_id' => $attendance->id,
                    'recorded_at' => $ping['at'],
                    'latitude' => round($ping['lat'], 7),
                    'longitude' => round($ping['lng'], 7),
                    'accuracy' => $ping['accuracy'],
                    'speed' => $ping['speed'],
                    'battery' => $ping['battery'] !== null ? max(0, min(100, $ping['battery'])) : null,
                    'segment_metres' => $segment,
                ]);
                $kept++;

                if ($segment !== null) {
                    $anchor = ['lat' => $ping['lat'], 'lng' => $ping['lng'], 'at' => $ping['at']];
                }
            }

            return $kept;
        });
    }

    /**
     * The point distance is measured from: the latest anchor ping, else the
     * check-in location itself.
     *
     * @return array{lat: float, lng: float, at: Carbon}|null
     */
    private function lastAnchor(TsoAttendance $attendance): ?array
    {
        $ping = LocationPing::query()
            ->where('tso_attendance_id', $attendance->id)
            ->whereNotNull('segment_metres')
            ->orderByDesc('recorded_at')->orderByDesc('id')
            ->first();

        if ($ping) {
            return ['lat' => $ping->latitude, 'lng' => $ping->longitude, 'at' => $ping->recorded_at];
        }

        if ($attendance->check_in_latitude !== null && $attendance->check_in_longitude !== null) {
            return [
                'lat' => (float) $attendance->check_in_latitude,
                'lng' => (float) $attendance->check_in_longitude,
                'at' => $attendance->check_in_at,
            ];
        }

        return null;
    }

    private function isImpossibleJump(float $metres, Carbon $from, Carbon $to): bool
    {
        $maxSpeedKmh = (float) config('tracking.max_speed_kmh');
        if ($maxSpeedKmh <= 0 || $metres < 1000) {
            return false;
        }

        $hours = max(1, $from->diffInSeconds($to, true)) / 3600;

        return ($metres / 1000) / $hours > $maxSpeedKmh;
    }

    /**
     * Start and end of a calendar day in the attendance timezone, as UTC instants.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dayBounds(string $date): array
    {
        $start = Carbon::parse($date, config('attendance.timezone'))->startOfDay();

        return [$start->copy()->utc(), $start->copy()->endOfDay()->utc()];
    }

    /**
     * One row per attendance on the date: distance, last known position, visit
     * count and live status. Used by the manager's overview map and table.
     *
     * @param  list<int>|null  $userIds  null = everyone
     * @return Collection<int, array<string, mixed>>
     */
    public function daySummaries(string $date, ?array $userIds, ?int $onlyUserId = null): Collection
    {
        $attendances = TsoAttendance::query()
            ->with('user.reportsTo')
            ->whereDate('attendance_date', $date)
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->when($onlyUserId, fn ($q, $id) => $q->where('user_id', $id))
            ->get();

        if ($attendances->isEmpty()) {
            return collect();
        }

        $ids = $attendances->pluck('id');

        $totals = LocationPing::query()
            ->whereIn('tso_attendance_id', $ids)
            ->groupBy('tso_attendance_id')
            ->selectRaw('tso_attendance_id, COUNT(*) as pings, COALESCE(SUM(segment_metres), 0) as metres, MAX(id) as last_id')
            ->get()->keyBy('tso_attendance_id');

        $lastPings = LocationPing::query()->whereIn('id', $totals->pluck('last_id'))->get()->keyBy('tso_attendance_id');

        [$from, $to] = $this->dayBounds($date);
        $visitCounts = PjpVisit::query()
            ->whereIn('user_id', $attendances->pluck('user_id'))
            ->whereBetween('visited_at', [$from, $to])
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as visits')
            ->pluck('visits', 'user_id');

        $liveAfter = now()->subMinutes((int) config('tracking.live_minutes'));

        return $attendances->map(function (TsoAttendance $a) use ($totals, $lastPings, $visitCounts, $liveAfter): array {
            $last = $lastPings->get($a->id);
            $lastLat = $a->isCheckedOut() && $a->check_out_latitude !== null ? (float) $a->check_out_latitude : ($last?->latitude ?? (float) $a->check_in_latitude);
            $lastLng = $a->isCheckedOut() && $a->check_out_longitude !== null ? (float) $a->check_out_longitude : ($last?->longitude ?? (float) $a->check_in_longitude);
            $lastSeen = $a->isCheckedOut() ? $a->check_out_at : ($last?->recorded_at ?? $a->check_in_at);

            $status = match (true) {
                $a->isCheckedOut() => 'checked_out',
                $lastSeen !== null && $lastSeen->greaterThanOrEqualTo($liveAfter) => 'live',
                default => 'idle',
            };

            return [
                'attendance' => $a,
                'user_id' => $a->user_id,
                'name' => $a->user?->name,
                'asm' => $a->user?->reportsTo?->name,
                'status' => $status,
                'last_lat' => $lastLat,
                'last_lng' => $lastLng,
                'last_seen' => $lastSeen,
                'battery' => $last?->battery,
                'km' => round(((float) ($totals->get($a->id)?->metres ?? 0)) / 1000, 2),
                'pings' => (int) ($totals->get($a->id)?->pings ?? 0),
                'visits' => (int) ($visitCounts[$a->user_id] ?? 0),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Everything the path view needs for one user's day.
     *
     * @return array{path: list<array{lat: float, lng: float, t: string}>, stops: list<array<string, mixed>>, visits: list<array<string, mixed>>, timeline: list<array<string, mixed>>, km: float, pings: int}
     */
    public function dayActivity(TsoAttendance $attendance): array
    {
        $tz = config('attendance.timezone');

        $pings = LocationPing::query()
            ->where('tso_attendance_id', $attendance->id)
            ->orderBy('recorded_at')->orderBy('id')
            ->get(['latitude', 'longitude', 'accuracy', 'recorded_at', 'segment_metres', 'speed', 'battery']);

        $path = collect();
        if ($attendance->check_in_latitude !== null) {
            $path->push(['lat' => (float) $attendance->check_in_latitude, 'lng' => (float) $attendance->check_in_longitude, 't' => $attendance->check_in_at->timezone($tz)->format('H:i')]);
        }
        foreach ($pings->filter->isAnchor() as $ping) {
            $path->push(['lat' => $ping->latitude, 'lng' => $ping->longitude, 't' => $ping->recorded_at->timezone($tz)->format('H:i')]);
        }
        if ($attendance->isCheckedOut() && $attendance->check_out_latitude !== null) {
            $path->push(['lat' => (float) $attendance->check_out_latitude, 'lng' => (float) $attendance->check_out_longitude, 't' => $attendance->check_out_at->timezone($tz)->format('H:i')]);
        } elseif ($pings->isNotEmpty() && ! $pings->last()->isAnchor()) {
            $path->push(['lat' => $pings->last()->latitude, 'lng' => $pings->last()->longitude, 't' => $pings->last()->recorded_at->timezone($tz)->format('H:i')]);
        }

        $visits = $this->visitsFor($attendance);
        $stops = $this->stops($pings, $visits);

        return [
            'path' => $path->values()->all(),
            'stops' => $stops,
            'visits' => $visits,
            'timeline' => $this->timeline($attendance, $visits, $stops),
            'km' => round($pings->sum('segment_metres') / 1000, 2),
            'pings' => $pings->count(),
        ];
    }

    /**
     * Retailer visits logged on the attendance day, with how far the check-in
     * was from the retailer's saved location.
     *
     * @return list<array<string, mixed>>
     */
    public function visitsFor(TsoAttendance $attendance): array
    {
        [$from, $to] = $this->dayBounds($attendance->attendance_date->toDateString());
        $matchMetres = (float) config('tracking.visit_match_metres');

        return DB::table('pjp_visits')
            ->leftJoin('retailers', 'retailers.code', '=', 'pjp_visits.rt_code')
            ->where('pjp_visits.user_id', $attendance->user_id)
            ->whereBetween('pjp_visits.visited_at', [$from, $to])
            ->orderBy('pjp_visits.visited_at')
            ->get([
                'pjp_visits.rt_code', 'pjp_visits.visited_at', 'pjp_visits.latitude', 'pjp_visits.longitude',
                'pjp_visits.accuracy', 'pjp_visits.note',
                'retailers.name as retailer_name', 'retailers.area', 'retailers.phone',
                'retailers.latitude as rt_lat', 'retailers.longitude as rt_lng',
            ])
            ->map(function (object $v) use ($matchMetres): array {
                $hasGps = $v->latitude !== null && $v->longitude !== null;
                $offset = $hasGps && $v->rt_lat !== null && $v->rt_lng !== null
                    ? AttendanceService::distanceMetres((float) $v->latitude, (float) $v->longitude, (float) $v->rt_lat, (float) $v->rt_lng)
                    : null;

                return [
                    'rt_code' => $v->rt_code,
                    'name' => $v->retailer_name,
                    'area' => $v->area,
                    'phone' => $v->phone,
                    'at' => Carbon::parse($v->visited_at, 'UTC'),
                    'lat' => $hasGps ? (float) $v->latitude : null,
                    'lng' => $hasGps ? (float) $v->longitude : null,
                    'accuracy' => $v->accuracy,
                    'note' => $v->note,
                    'offset_metres' => $offset !== null ? (int) round($offset) : null,
                    'at_store' => $offset !== null ? $offset <= $matchMetres : null,
                ];
            })
            ->all();
    }

    /**
     * Places the user stayed within `stop_radius_metres` for `stop_minutes` or
     * longer, each tagged with the retailer visited during it, if any.
     *
     * @param  Collection<int, LocationPing>  $pings
     * @param  list<array<string, mixed>>  $visits
     * @return list<array{lat: float, lng: float, from: Carbon, to: Carbon, minutes: int, rt_code: ?string, retailer: ?string}>
     */
    public function stops(Collection $pings, array $visits = []): array
    {
        $radius = (float) config('tracking.stop_radius_metres');
        $minMinutes = (int) config('tracking.stop_minutes');
        $pings = $pings->values();
        $stops = [];
        $i = 0;

        while ($i < $pings->count()) {
            $start = $pings[$i];
            $j = $i;
            while ($j + 1 < $pings->count()
                && AttendanceService::distanceMetres($start->latitude, $start->longitude, $pings[$j + 1]->latitude, $pings[$j + 1]->longitude) <= $radius) {
                $j++;
            }

            $minutes = (int) $start->recorded_at->diffInMinutes($pings[$j]->recorded_at, true);
            if ($j > $i && $minutes >= $minMinutes) {
                $cluster = $pings->slice($i, $j - $i + 1);
                $from = $start->recorded_at;
                $to = $pings[$j]->recorded_at;
                $visit = collect($visits)->first(fn (array $v): bool => $v['at']->betweenIncluded($from->copy()->subMinutes(5), $to->copy()->addMinutes(5)));

                $stops[] = [
                    'lat' => round($cluster->avg('latitude'), 7),
                    'lng' => round($cluster->avg('longitude'), 7),
                    'from' => $from,
                    'to' => $to,
                    'minutes' => $minutes,
                    'rt_code' => $visit['rt_code'] ?? null,
                    'retailer' => $visit['name'] ?? null,
                ];
                $i = $j + 1;
            } else {
                $i++;
            }
        }

        return $stops;
    }

    /**
     * @param  list<array<string, mixed>>  $visits
     * @param  list<array<string, mixed>>  $stops
     * @return list<array{type: string, at: Carbon, title: string, detail: ?string, lat: ?float, lng: ?float}>
     */
    private function timeline(TsoAttendance $attendance, array $visits, array $stops): array
    {
        $events = collect([[
            'type' => 'check_in',
            'at' => $attendance->check_in_at,
            'title' => 'Checked in',
            'detail' => $attendance->check_in_address,
            'lat' => $attendance->check_in_latitude !== null ? (float) $attendance->check_in_latitude : null,
            'lng' => $attendance->check_in_longitude !== null ? (float) $attendance->check_in_longitude : null,
        ]]);

        foreach ($visits as $v) {
            $events->push([
                'type' => 'visit',
                'at' => $v['at'],
                'title' => 'Visited '.$v['rt_code'].($v['name'] ? ' · '.$v['name'] : ''),
                'detail' => $v['note'],
                'lat' => $v['lat'],
                'lng' => $v['lng'],
            ]);
        }

        foreach ($stops as $s) {
            $events->push([
                'type' => 'stop',
                'at' => $s['from'],
                'title' => 'Stopped '.$s['minutes'].' min'.($s['rt_code'] ? ' at '.$s['rt_code'] : ''),
                'detail' => $s['retailer'],
                'lat' => $s['lat'],
                'lng' => $s['lng'],
            ]);
        }

        if ($attendance->isCheckedOut()) {
            $events->push([
                'type' => 'check_out',
                'at' => $attendance->check_out_at,
                'title' => 'Checked out'.($attendance->workingLabel() ? ' · worked '.$attendance->workingLabel() : ''),
                'detail' => $attendance->check_out_address,
                'lat' => $attendance->check_out_latitude !== null ? (float) $attendance->check_out_latitude : null,
                'lng' => $attendance->check_out_longitude !== null ? (float) $attendance->check_out_longitude : null,
            ]);
        }

        return $events->sortBy(fn (array $e): int => $e['at']->getTimestamp())->values()->all();
    }
}
