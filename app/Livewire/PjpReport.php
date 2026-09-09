<?php

namespace App\Livewire;

use App\Models\Pjp as PjpModel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * PJP reporting — one row per PJP with planned vs actual. Rendered as a tab
 * inside the PJP page. ASMs see only their own TSOs.
 */
class PjpReport extends Component
{
    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $month = null;

    #[Url]
    public ?int $tsoId = null;

    #[Url]
    public ?int $asmId = null;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('pjp.report'), 403);
        $tz = config('pjp.timezone');
        $this->year ??= (int) Carbon::now($tz)->year;
        $this->month ??= (int) Carbon::now($tz)->month;
    }

    public function render()
    {
        $me = auth()->user();
        $scopedToAsm = $me->hasRole('ASM') && ! $me->hasAnyRole(['Super Admin', 'Admin', 'NSM']);

        $pjps = PjpModel::query()
            ->with(['tso', 'asm', 'nsm'])
            ->withCount('visits')
            ->when($this->year, fn ($q, $v) => $q->where('year', $v))
            ->when($this->month, fn ($q, $v) => $q->where('month', $v))
            ->when($this->tsoId, fn ($q, $v) => $q->where('tso_id', $v))
            ->when($this->asmId, fn ($q, $v) => $q->where('asm_id', $v))
            ->when($this->status, fn ($q, $v) => $q->where('status', $v))
            ->when($scopedToAsm, fn ($q) => $q->where('asm_id', $me->id))
            ->orderByDesc('year')->orderByDesc('month')
            ->get()
            ->map(function (PjpModel $p) {
                $p->achievement = $p->planned_visits > 0
                    ? round($p->visits_count / $p->planned_visits * 100, 1)
                    : null;

                return $p;
            });

        return view('livewire.pjp-report', [
            'pjps' => $pjps,
            'statuses' => config('pjp.statuses'),
            'tsoOptions' => User::role('TSO')->orderBy('name')->pluck('name', 'id'),
            'asmOptions' => User::role('ASM')->orderBy('name')->pluck('name', 'id'),
            'totals' => [
                'plans' => $pjps->count(),
                'days' => $pjps->sum('planned_days'),
                'visits' => $pjps->sum('planned_visits'),
                'actual' => $pjps->sum('visits_count'),
            ],
        ]);
    }
}
