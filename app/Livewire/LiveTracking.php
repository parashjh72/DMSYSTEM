<?php

namespace App\Livewire;

use App\Models\LocationPing;
use App\Models\PjpVisit;
use App\Models\TsoAttendance;
use App\Models\User;
use App\Services\LocationTrackingService;
use App\Support\MapConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Field-force live tracking for managers: everyone's last known position on
 * a date, and for one TSO the day's GPS route, distance travelled, stops,
 * retailer visits (meeting locations) and an activity timeline.
 */
#[Layout('components.layouts.app')]
#[Title('Live Tracking')]
class LiveTracking extends Component
{
    #[Url]
    public string $date = '';

    #[Url(as: 'user')]
    public ?int $userId = null;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('tracking.view'), 403);
        $this->date = $this->validDate($this->date) ?? $this->today();
        $this->guardUser();
    }

    public function updatedDate(): void
    {
        $this->date = $this->validDate($this->date) ?? $this->today();
    }

    public function updatedUserId(): void
    {
        $this->userId = $this->userId ?: null;
        $this->guardUser();
    }

    public function showUser(int $userId): void
    {
        $this->userId = $userId;
        $this->guardUser();
    }

    public function backToAll(): void
    {
        $this->userId = null;
    }

    public function shiftDay(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
    }

    private function today(): string
    {
        return Carbon::now(config('attendance.timezone'))->toDateString();
    }

    private function validDate(string $value): ?string
    {
        try {
            return $value !== '' ? Carbon::parse($value)->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** ASMs see only their own team; Admin / NSM / Super Admin see everyone. */
    private function visibleUserIds(): ?array
    {
        $me = auth()->user();
        if ($me->hasAnyRole(['Super Admin', 'Admin', 'NSM'])) {
            return null;
        }

        return $me->subordinates()->pluck('id')->push($me->id)->all();
    }

    private function guardUser(): void
    {
        $visible = $this->visibleUserIds();
        abort_if($this->userId !== null && $visible !== null && ! in_array($this->userId, $visible, true), 403);
    }

    private function isToday(): bool
    {
        return $this->date === $this->today();
    }

    /** CSV of the selected TSO's breadcrumbs, or of the whole team's day summary. */
    public function export(LocationTrackingService $tracking): StreamedResponse
    {
        abort_unless(auth()->user()?->can('exports.view'), 403);
        $this->guardUser();
        $tz = config('attendance.timezone');

        if ($this->userId) {
            $attendance = $this->attendance();
            $pings = $attendance
                ? LocationPing::where('tso_attendance_id', $attendance->id)->orderBy('recorded_at')->get()
                : collect();
            $name = User::find($this->userId)?->name ?? 'user';

            return $this->csv('gps-path-'.str($name)->slug().'-'.$this->date.'.csv',
                ['Time', 'Latitude', 'Longitude', 'Accuracy (m)', 'Speed (km/h)', 'Battery %', 'Segment (m)'],
                $pings->map(fn (LocationPing $p): array => [
                    $p->recorded_at->timezone($tz)->format('Y-m-d H:i:s'),
                    $p->latitude, $p->longitude, $p->accuracy,
                    $p->speed !== null ? round($p->speed * 3.6, 1) : null,
                    $p->battery,
                    $p->segment_metres !== null ? round($p->segment_metres) : null,
                ]));
        }

        return $this->csv('field-tracking-'.$this->date.'.csv',
            ['Date', 'TSO', 'ASM', 'Check In', 'Check Out', 'Working', 'Distance (km)', 'Visits', 'Status', 'Last Seen', 'Last Lat', 'Last Lng'],
            $tracking->daySummaries($this->date, $this->visibleUserIds())->map(fn (array $r): array => [
                $this->date, $r['name'], $r['asm'],
                $r['attendance']->check_in_at?->timezone($tz)->format('H:i'),
                $r['attendance']->check_out_at?->timezone($tz)->format('H:i'),
                $r['attendance']->workingLabel(),
                $r['km'], $r['visits'], $r['status'],
                $r['last_seen']?->timezone($tz)->format('H:i'),
                $r['last_lat'], $r['last_lng'],
            ]));
    }

    /**
     * @param  list<string>  $header
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    private function csv(string $filename, array $header, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function attendance(): ?TsoAttendance
    {
        return TsoAttendance::query()
            ->with('user.reportsTo')
            ->where('user_id', $this->userId)
            ->whereDate('attendance_date', $this->date)
            ->first();
    }

    /**
     * The selected TSO's last 14 working days, for jumping between days.
     *
     * @return Collection<int, array{date: string, check_in: ?Carbon, check_out: ?Carbon, working: ?string, km: float, visits: int}>
     */
    private function recentDays(LocationTrackingService $tracking): Collection
    {
        $tz = config('attendance.timezone');
        $to = Carbon::parse($this->date);
        $from = $to->copy()->subDays(13);

        $days = TsoAttendance::query()
            ->where('user_id', $this->userId)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderByDesc('attendance_date')
            ->get();

        $metres = LocationPing::query()
            ->whereIn('tso_attendance_id', $days->pluck('id'))
            ->groupBy('tso_attendance_id')
            ->selectRaw('tso_attendance_id, COALESCE(SUM(segment_metres), 0) as metres')
            ->pluck('metres', 'tso_attendance_id');

        [$rangeStart] = $tracking->dayBounds($from->toDateString());
        [, $rangeEnd] = $tracking->dayBounds($to->toDateString());
        $visits = PjpVisit::query()
            ->where('user_id', $this->userId)
            ->whereBetween('visited_at', [$rangeStart, $rangeEnd])
            ->pluck('visited_at')
            ->countBy(fn (Carbon $at): string => $at->timezone($tz)->toDateString());

        return $days->map(fn (TsoAttendance $a): array => [
            'date' => $a->attendance_date->toDateString(),
            'check_in' => $a->check_in_at,
            'check_out' => $a->check_out_at,
            'working' => $a->workingLabel(),
            'km' => round(((float) ($metres[$a->id] ?? 0)) / 1000, 2),
            'visits' => (int) ($visits[$a->attendance_date->toDateString()] ?? 0),
        ]);
    }

    public function render(LocationTrackingService $tracking)
    {
        $tz = config('attendance.timezone');
        $visible = $this->visibleUserIds();

        $userOptions = User::role('TSO')
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))
            ->orderBy('name')->pluck('name', 'id');

        $data = [
            'tz' => $tz,
            'isToday' => $this->isToday(),
            'userOptions' => $userOptions,
            'mapsEnabled' => MapConfig::enabled(),
            'apiKey' => MapConfig::apiKey(),
            'centre' => MapConfig::defaultCentre(),
            'liveMinutes' => (int) config('tracking.live_minutes'),
        ];

        if ($this->userId) {
            $attendance = $this->attendance();
            $activity = $attendance ? $tracking->dayActivity($attendance) : null;
            $summary = $attendance ? $tracking->daySummaries($this->date, null, $this->userId)->first() : null;

            return view('livewire.live-tracking', $data + [
                'mode' => 'path',
                'person' => User::with('reportsTo')->find($this->userId),
                'attendance' => $attendance,
                'activity' => $activity,
                'summary' => $summary,
                'recentDays' => $this->recentDays($tracking),
                'mapPayload' => [
                    'mode' => 'path',
                    'key' => $this->userId.'|'.$this->date,
                    'path' => $activity['path'] ?? [],
                    'current' => $summary && $summary['status'] !== 'checked_out'
                        ? ['lat' => $summary['last_lat'], 'lng' => $summary['last_lng'], 'label' => 'Last seen '.$summary['last_seen']?->timezone($tz)->format('H:i')]
                        : null,
                    'checkIn' => $attendance?->check_in_latitude !== null
                        ? ['lat' => (float) $attendance->check_in_latitude, 'lng' => (float) $attendance->check_in_longitude, 'label' => 'Check-in '.$attendance->check_in_at->timezone($tz)->format('H:i')]
                        : null,
                    'checkOut' => $attendance?->isCheckedOut() && $attendance->check_out_latitude !== null
                        ? ['lat' => (float) $attendance->check_out_latitude, 'lng' => (float) $attendance->check_out_longitude, 'label' => 'Check-out '.$attendance->check_out_at->timezone($tz)->format('H:i')]
                        : null,
                    'visits' => collect($activity['visits'] ?? [])->filter(fn (array $v): bool => $v['lat'] !== null)->map(fn (array $v): array => [
                        'lat' => $v['lat'], 'lng' => $v['lng'],
                        'label' => $v['rt_code'].($v['name'] ? ' · '.$v['name'] : ''),
                        'time' => $v['at']->timezone($tz)->format('H:i'),
                        'atStore' => $v['at_store'],
                    ])->values()->all(),
                    'stops' => collect($activity['stops'] ?? [])->map(fn (array $s): array => [
                        'lat' => $s['lat'], 'lng' => $s['lng'],
                        'label' => $s['minutes'].' min stop'.($s['rt_code'] ? ' at '.$s['rt_code'] : ''),
                        'time' => $s['from']->timezone($tz)->format('H:i').'–'.$s['to']->timezone($tz)->format('H:i'),
                    ])->values()->all(),
                ],
            ]);
        }

        $rows = $tracking->daySummaries($this->date, $visible)
            ->when($this->status !== '', fn (Collection $c) => $c->where('status', $this->status)->values());

        $checkedIn = TsoAttendance::query()->whereDate('attendance_date', $this->date)
            ->when($visible !== null, fn ($q) => $q->whereIn('user_id', $visible))->pluck('user_id');

        return view('livewire.live-tracking', $data + [
            'mode' => 'overview',
            'rows' => $rows,
            'counts' => [
                'live' => $rows->where('status', 'live')->count(),
                'idle' => $rows->where('status', 'idle')->count(),
                'checked_out' => $rows->where('status', 'checked_out')->count(),
                'absent' => $userOptions->keys()->diff($checkedIn)->count(),
                'km' => round($rows->sum('km'), 1),
                'visits' => $rows->sum('visits'),
            ],
            'mapPayload' => [
                'mode' => 'overview',
                'key' => 'all|'.$this->date.'|'.$this->status,
                'people' => $rows->filter(fn (array $r): bool => $r['last_lat'] !== null && $r['last_lng'] !== null)->map(fn (array $r): array => [
                    'id' => $r['user_id'],
                    'lat' => $r['last_lat'], 'lng' => $r['last_lng'],
                    'name' => $r['name'],
                    'status' => $r['status'],
                    'seen' => $r['last_seen']?->timezone($tz)->format('H:i'),
                    'km' => $r['km'],
                    'visits' => $r['visits'],
                ])->values()->all(),
            ],
        ]);
    }
}
