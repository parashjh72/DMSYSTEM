<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\DailyRoute;
use App\Models\FieldSales\LocationPing;
use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Today's position and duty state of every field user a manager may see —
 * the data behind the live staff map.
 */
class LiveStaff
{
    /** After this long without a ping a checked-in user is shown as offline. */
    public const OFFLINE_AFTER_MINUTES = 60;

    public const STATES = [
        'active' => 'Active',
        'delayed' => 'Signal delayed',
        'offline' => 'Offline',
        'checked_out' => 'Checked out',
        'not_checked_in' => 'Not checked in',
    ];

    public function __construct(private FieldStaff $staff, private PolicyResolver $policies) {}

    /**
     * @return list<array{id: int, name: string, rd_codes: list<string>, state: string, check_in_at: ?string, check_out_at: ?string, km: float, last: ?array{lat: float, lng: float, at: string, minutes_ago: int, accuracy: ?float, battery: ?int}}>
     */
    public function snapshot(User $viewer, ?int $regionId = null, ?int $areaId = null, ?string $rdCode = null): array
    {
        $tz = config('field_sales.timezone');
        $today = Carbon::now($tz)->startOfDay();
        $users = $this->staff->query($viewer, $regionId, $areaId, $rdCode)->get();
        $ids = $users->pluck('id');

        $attendances = TsoAttendance::query()
            ->whereIn('user_id', $ids)
            ->whereDate('attendance_date', $today->toDateString())
            ->get()
            ->keyBy('user_id');

        $latestIds = LocationPing::query()
            ->whereIn('user_id', $ids)
            ->where('recorded_at', '>=', $today->copy()->utc())
            ->where('is_suspect', false)
            ->where(fn ($q) => $q->whereNull('accuracy')->orWhere('accuracy', '<=', config('field_sales.tracking.max_accuracy_metres')))
            ->groupBy('user_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');
        $latest = LocationPing::query()->whereIn('id', $latestIds)->get()->keyBy('user_id');

        $routes = DailyRoute::query()
            ->whereIn('user_id', $ids)
            ->whereDate('route_date', $today->toDateString())
            ->pluck('distance_km', 'user_id');

        return $users->map(function (User $user) use ($attendances, $latest, $routes, $tz) {
            $attendance = $attendances->get($user->id);
            $ping = $latest->get($user->id);
            $minutesAgo = $ping ? (int) $ping->recorded_at->diffInMinutes(now(), true) : null;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'rd_codes' => $user->scopedRdCodes(),
                'state' => $this->state($user, $attendance, $minutesAgo),
                'check_in_at' => $attendance?->check_in_at?->setTimezone($tz)->format('H:i'),
                'check_out_at' => $attendance?->check_out_at?->setTimezone($tz)->format('H:i'),
                'km' => (float) ($routes[$user->id] ?? 0),
                'last' => $ping ? [
                    'lat' => $ping->latitude,
                    'lng' => $ping->longitude,
                    'at' => $ping->recorded_at->setTimezone($tz)->format('H:i'),
                    'minutes_ago' => $minutesAgo,
                    'accuracy' => $ping->accuracy,
                    'battery' => $ping->battery,
                ] : null,
            ];
        })->values()->all();
    }

    private function state(User $user, ?TsoAttendance $attendance, ?int $minutesAgo): string
    {
        if ($attendance === null) {
            return 'not_checked_in';
        }
        if ($attendance->check_out_at !== null) {
            return 'checked_out';
        }
        if ($minutesAgo === null || $minutesAgo > self::OFFLINE_AFTER_MINUTES) {
            return 'offline';
        }

        $interval = $this->policies->forUser($user)->ping_interval_minutes;

        return $minutesAgo <= ($interval * 2) + 1 ? 'active' : 'delayed';
    }
}
