<?php

namespace App\Livewire\FieldSales;

use App\Livewire\FieldSales\Concerns\WithHierarchyFilters;
use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\DailyRoute;
use App\Models\User;
use App\Services\FieldSales\AttendanceDayBuilder;
use App\Services\FieldSales\FieldStaff;
use App\Support\NepaliDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('Monthly Attendance')]
class AttendanceMonthly extends Component
{
    use WithHierarchyFilters, WithPagination;

    /** "Y-m" */
    #[Url]
    public string $month = '';

    public ?string $flash = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('fs.reports.view'), 403);
        $this->month = $this->month ?: Carbon::now(config('field_sales.timezone'))->format('Y-m');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** Re-works out the month's statuses for the filtered staff (the nightly job does this too). */
    public function recalculate(AttendanceDayBuilder $builder): void
    {
        abort_unless(auth()->user()?->can('fs.reports.view'), 403);

        [$from, $to] = $this->range();
        $written = 0;
        $this->staffQuery()->chunkById(100, function ($users) use ($builder, $from, $to, &$written) {
            $written += $builder->build($users, $from, $to);
        });

        $this->flash = "Recalculated {$written} attendance day(s).";
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('fs.reports.view'), 403);

        [$from, $to] = $this->range();
        $users = $this->staffQuery()->get();
        [$grid, $km] = $this->gridFor($users, $from, $to);
        $dates = $this->dates($from, $to);

        return response()->streamDownload(function () use ($users, $grid, $km, $dates) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Distributors', ...array_map(fn (Carbon $d) => $d->format('d').' ('.NepaliDate::format($d).')', $dates),
                'Present', 'Late', 'Half day', 'Absent', 'Leave', 'Weekly off', 'Holiday', 'Km'], ',', '"', '');
            foreach ($users as $user) {
                $days = $grid[$user->id] ?? [];
                $totals = $this->totals($days);
                fputcsv($out, [
                    $user->name,
                    implode(' ', $user->scopedRdCodes()),
                    ...array_map(fn (Carbon $d) => AttendanceDay::STATUSES[$days[$d->toDateString()]->status ?? '__'][0] ?? '', $dates),
                    $totals['present'], $totals['late'], $totals['half_day'], $totals['absent'],
                    $totals['leave'], $totals['weekly_off'], $totals['holiday'],
                    number_format((float) ($km[$user->id] ?? 0), 2, '.', ''),
                ], ',', '"', '');
            }
            fclose($out);
        }, 'field-attendance-'.$this->month.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        $tz = config('field_sales.timezone');
        $start = rescue(fn () => Carbon::createFromFormat('Y-m-d', $this->month.'-01', $tz), Carbon::now($tz), report: false)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()->startOfDay()];
    }

    /** @return list<Carbon> */
    private function dates(Carbon $from, Carbon $to): array
    {
        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dates[] = $d->copy();
        }

        return $dates;
    }

    private function staffQuery()
    {
        return app(FieldStaff::class)->query(auth()->user(), $this->regionId, $this->areaId, $this->rdCode ?: null);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{0: array<int, array<string, AttendanceDay>>, 1: Collection<int, float>}
     */
    private function gridFor(Collection $users, Carbon $from, Carbon $to): array
    {
        $ids = $users->pluck('id');

        $grid = AttendanceDay::query()
            ->whereIn('user_id', $ids)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $days) => $days->keyBy(fn (AttendanceDay $d) => $d->attendance_date->toDateString())->all())
            ->all();

        $km = DailyRoute::query()
            ->whereIn('user_id', $ids)
            ->whereDate('route_date', '>=', $from->toDateString())
            ->whereDate('route_date', '<=', $to->toDateString())
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(distance_km) as km')
            ->pluck('km', 'user_id');

        return [$grid, $km];
    }

    /**
     * @param  array<string, AttendanceDay>  $days
     * @return array<string, int>
     */
    private function totals(array $days): array
    {
        $counts = array_fill_keys(array_keys(AttendanceDay::STATUSES), 0);
        foreach ($days as $day) {
            if ($day->status !== null) {
                $counts[$day->status]++;
            }
        }

        return $counts;
    }

    public function render()
    {
        [$from, $to] = $this->range();
        $users = $this->staffQuery()->paginate(50);
        [$grid, $km] = $this->gridFor($users->getCollection(), $from, $to);

        $bsFrom = NepaliDate::monthLabel($from);
        $bsTo = NepaliDate::monthLabel($to);

        return view('livewire.field-sales.attendance-monthly', [
            'users' => $users,
            'grid' => $grid,
            'km' => $km,
            'dates' => $this->dates($from, $to),
            'totalsFor' => fn (array $days) => $this->totals($days),
            'options' => $this->hierarchyOptions(),
            'bsLabel' => $bsFrom === $bsTo ? $bsFrom : $bsFrom.' – '.$bsTo,
            'today' => Carbon::now(config('field_sales.timezone'))->toDateString(),
        ]);
    }
}
