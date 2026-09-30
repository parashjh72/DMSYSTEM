<?php

namespace App\Livewire;

use App\Models\PjpVisit;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Beat visit reporting, rendered as a tab inside the Beat Plan page:
 * every party visit with its outcome and order, how often each party is
 * planned vs actually visited, and why visits ended without an order.
 * ASMs see only their own TSOs.
 */
class BeatVisitReport extends Component
{
    use WithPagination;

    #[Url(as: 'bv')]
    public string $view = 'visits';

    #[Url(as: 'bv_from')]
    public string $from = '';

    #[Url(as: 'bv_to')]
    public string $to = '';

    #[Url(as: 'bv_tso')]
    public ?int $tsoId = null;

    #[Url(as: 'bv_outcome')]
    public string $outcome = '';

    #[Url(as: 'bv_q')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('pjp.report'), 403);
        $tz = config('pjp.timezone');
        $this->from = $this->from ?: Carbon::now($tz)->startOfMonth()->toDateString();
        $this->to = $this->to ?: Carbon::now($tz)->toDateString();
        $this->view = in_array($this->view, ['visits', 'frequency', 'reasons'], true) ? $this->view : 'visits';
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    private function asmScope(): ?int
    {
        $me = auth()->user();

        return $me->hasRole('ASM') && ! $me->hasAnyRole(['Super Admin', 'Admin', 'NSM']) ? $me->id : null;
    }

    /** Beat days in range, scoped to the viewer and the TSO filter. */
    private function days(): Builder
    {
        return DB::table('pjp_days')
            ->join('pjps', 'pjps.id', '=', 'pjp_days.pjp_id')
            ->whereDate('pjp_days.plan_date', '>=', $this->from)
            ->whereDate('pjp_days.plan_date', '<=', $this->to)
            ->when($this->asmScope(), fn ($q, $id) => $q->where('pjps.asm_id', $id))
            ->when($this->tsoId, fn ($q, $id) => $q->where('pjps.tso_id', $id));
    }

    private function visits(): Builder
    {
        return $this->days()
            ->join('pjp_visits', 'pjp_visits.pjp_day_id', '=', 'pjp_days.id')
            ->leftJoin('retailers', 'retailers.code', '=', 'pjp_visits.rt_code')
            ->leftJoin('users', 'users.id', '=', 'pjps.tso_id')
            ->when($this->outcome === 'unrecorded', fn ($q) => $q->whereNull('pjp_visits.outcome'))
            ->when(in_array($this->outcome, [PjpVisit::EFFECTIVE, PjpVisit::NON_EFFECTIVE], true), fn ($q) => $q->where('pjp_visits.outcome', $this->outcome))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('pjp_visits.rt_code', 'like', trim($this->search).'%')
                ->orWhere('retailers.name', 'like', '%'.trim($this->search).'%')));
    }

    private function visitRows(): Builder
    {
        return $this->visits()
            ->orderByDesc('pjp_visits.visited_at')
            ->select([
                'pjp_visits.id', 'pjp_visits.rt_code', 'pjp_visits.visited_at', 'pjp_visits.outcome',
                'pjp_visits.order_items', 'pjp_visits.order_value', 'pjp_visits.no_order_reason', 'pjp_visits.note',
                'pjp_visits.latitude', 'pjp_visits.longitude', 'pjp_days.plan_date',
                'retailers.name as retailer_name', 'retailers.area', 'users.name as tso_name',
            ]);
    }

    /** Per party: planned calls vs actual visits, outcome split, order value, last visit. */
    private function frequencyRows(): Builder
    {
        $visits = DB::table('pjp_visits')
            ->selectRaw('pjp_day_id, rt_code, visited_at, outcome, order_value');

        return $this->days()
            ->join('pjp_day_retailers', 'pjp_day_retailers.pjp_day_id', '=', 'pjp_days.id')
            ->leftJoinSub($visits, 'v', fn ($j) => $j->on('v.pjp_day_id', '=', 'pjp_days.id')->on('v.rt_code', '=', 'pjp_day_retailers.rt_code'))
            ->leftJoin('retailers', 'retailers.code', '=', 'pjp_day_retailers.rt_code')
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('pjp_day_retailers.rt_code', 'like', trim($this->search).'%')
                ->orWhere('retailers.name', 'like', '%'.trim($this->search).'%')))
            ->groupBy('pjp_day_retailers.rt_code', 'retailers.name', 'retailers.area')
            ->selectRaw("pjp_day_retailers.rt_code, retailers.name as retailer_name, retailers.area,
                COUNT(*) as planned,
                COUNT(v.visited_at) as visited,
                SUM(CASE WHEN v.outcome = 'effective' THEN 1 ELSE 0 END) as effective,
                SUM(CASE WHEN v.outcome = 'non_effective' THEN 1 ELSE 0 END) as non_effective,
                COALESCE(SUM(v.order_value), 0) as order_value,
                MAX(v.visited_at) as last_visit")
            ->orderByDesc('planned')->orderBy('pjp_day_retailers.rt_code');
    }

    /** @return Collection<int, object> */
    private function reasonRows(): Collection
    {
        $labels = config('pjp.no_order_reasons');
        $rows = $this->visits()->where('pjp_visits.outcome', PjpVisit::NON_EFFECTIVE)
            ->groupBy('pjp_visits.no_order_reason')
            ->selectRaw('pjp_visits.no_order_reason as reason, COUNT(*) as visits')
            ->orderByDesc('visits')->get();
        $total = max(1, $rows->sum('visits'));

        return $rows->map(fn (object $r): object => (object) [
            'reason' => $labels[$r->reason] ?? ($r->reason ?: 'Not given'),
            'visits' => (int) $r->visits,
            'share' => round($r->visits / $total * 100, 1),
        ]);
    }

    /** @return Collection<int, object> */
    private function tsoRows(): Collection
    {
        return $this->visits()
            ->groupBy('pjps.tso_id', 'users.name')
            ->selectRaw("users.name as tso_name, COUNT(*) as visits,
                SUM(CASE WHEN pjp_visits.outcome = 'effective' THEN 1 ELSE 0 END) as effective,
                SUM(CASE WHEN pjp_visits.outcome = 'non_effective' THEN 1 ELSE 0 END) as non_effective,
                COALESCE(SUM(pjp_visits.order_value), 0) as order_value")
            ->orderByDesc('visits')->get();
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('exports.view'), 403);
        $tz = config('pjp.timezone');
        $reasons = config('pjp.no_order_reasons');

        [$header, $rows] = match ($this->view) {
            'frequency' => [
                ['RT Code', 'Party', 'Area', 'Planned Calls', 'Visited', 'Effective', 'Non-effective', 'Missed', 'Order Value', 'Last Visit'],
                $this->frequencyRows()->get()->map(fn (object $r): array => [
                    $r->rt_code, $r->retailer_name, $r->area, $r->planned, $r->visited, $r->effective, $r->non_effective,
                    $r->planned - $r->visited, $r->order_value,
                    $r->last_visit ? Carbon::parse($r->last_visit, 'UTC')->timezone($tz)->format('Y-m-d H:i') : null,
                ]),
            ],
            'reasons' => [
                ['Reason', 'Visits', 'Share %'],
                $this->reasonRows()->map(fn (object $r): array => [$r->reason, $r->visits, $r->share]),
            ],
            default => [
                ['Visited At', 'Beat Date', 'TSO', 'RT Code', 'Party', 'Area', 'Outcome', 'Order Items', 'Order Value', 'No-order Reason', 'Remarks', 'Latitude', 'Longitude'],
                $this->visitRows()->get()->map(fn (object $r): array => [
                    Carbon::parse($r->visited_at, 'UTC')->timezone($tz)->format('Y-m-d H:i'),
                    $r->plan_date, $r->tso_name, $r->rt_code, $r->retailer_name, $r->area,
                    match ($r->outcome) {
                        'effective' => 'Effective', 'non_effective' => 'Non-effective', default => 'Not recorded'
                    },
                    collect(json_decode((string) $r->order_items, true) ?: [])->map(fn (array $l): string => $l['model'].' x'.$l['qty'])->implode('; '),
                    $r->order_value,
                    $r->no_order_reason ? ($reasons[$r->no_order_reason] ?? $r->no_order_reason) : null,
                    $r->note, $r->latitude, $r->longitude,
                ]),
            ],
        };

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, 'beat-'.$this->view.'-'.$this->from.'_'.$this->to.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $summary = $this->visits()
            ->selectRaw("COUNT(*) as visits,
                SUM(CASE WHEN pjp_visits.outcome = 'effective' THEN 1 ELSE 0 END) as effective,
                SUM(CASE WHEN pjp_visits.outcome = 'non_effective' THEN 1 ELSE 0 END) as non_effective,
                COALESCE(SUM(pjp_visits.order_value), 0) as order_value")
            ->first();
        $planned = (clone $this->days())->join('pjp_day_retailers', 'pjp_day_retailers.pjp_day_id', '=', 'pjp_days.id')->count();

        $tsoOptions = User::role('TSO')
            ->when($this->asmScope(), fn ($q, $id) => $q->where('reports_to_id', $id))
            ->orderBy('name')->pluck('name', 'id');

        return view('livewire.beat-visit-report', [
            'tz' => config('pjp.timezone'),
            'reasons' => config('pjp.no_order_reasons'),
            'tsoOptions' => $tsoOptions,
            'totals' => [
                'planned' => $planned,
                'visits' => (int) $summary->visits,
                'effective' => (int) $summary->effective,
                'non_effective' => (int) $summary->non_effective,
                'order_value' => (float) $summary->order_value,
                'coverage' => $planned > 0 ? round($summary->visits / $planned * 100, 1) : null,
                'strike_rate' => $summary->visits > 0 ? round($summary->effective / $summary->visits * 100, 1) : null,
            ],
            'rows' => match ($this->view) {
                'frequency' => $this->frequencyRows()->paginate(50),
                'reasons' => null,
                default => $this->visitRows()->paginate(50),
            },
            'reasonRows' => $this->view === 'reasons' ? $this->reasonRows() : collect(),
            'tsoRows' => $this->view === 'reasons' ? $this->tsoRows() : collect(),
        ]);
    }
}
