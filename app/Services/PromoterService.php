<?php

namespace App\Services;

use App\Models\Promoter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Promoter ("RA") monthly achievement: for each promoter, their assigned
 * retailer's device count in the month on the configured basis, against the
 * promoter's target — computed in a single grouped query.
 */
class PromoterService
{
    /**
     * @return array{
     *   month: string, from: string, to: string,
     *   rows: Collection<int, array<string, mixed>>,
     *   totals: array{target:int, achieved:int, pct: ?float}
     * }
     */
    public function achievement(?string $month = null, ?string $type = null, ?string $rdCode = null, bool $activeOnly = true): array
    {
        $anchor = $month ? Carbon::parse($month.'-01') : Carbon::now(config('reports.timezone', 'Asia/Kathmandu'))->startOfMonth();
        $from = $anchor->copy()->startOfMonth()->toDateString();
        $to = $anchor->copy()->endOfMonth()->toDateString();

        $promoters = Promoter::query()
            ->when($activeOnly, fn ($q) => $q->where('active', true))
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->when($rdCode, fn ($q, $c) => $q->where('rd_code', $c))
            ->orderBy('name')
            ->get();

        $basis = config('promoters.achievement_basis', 'activation_date');
        $rtCodes = $promoters->pluck('rt_code')->filter()->unique()->values();

        $counts = $rtCodes->isEmpty() ? collect() : collect(DB::select(
            'SELECT rt_code, COUNT(*) AS achieved
               FROM sales_activation_records
              WHERE rt_code IN ('.$rtCodes->map(fn () => '?')->implode(',').")
                AND {$basis} BETWEEN ? AND ?"
            .($basis === 'activation_date' ? ' AND is_activated = 1' : '')
            .' GROUP BY rt_code',
            [...$rtCodes->all(), $from, $to],
        ))->keyBy('rt_code');

        $rows = $promoters->map(function (Promoter $p) use ($counts) {
            $achieved = (int) ($counts->get($p->rt_code)->achieved ?? 0);
            $target = (int) $p->monthly_target;
            $pct = $target > 0 ? round($achieved / $target * 100, 1) : null;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $p->phone,
                'type' => $p->type,
                'type_label' => $p->typeLabel(),
                'rt_code' => $p->rt_code,
                'rt_name' => $p->rt_name,
                'rd_code' => $p->rd_code,
                'target' => $target,
                'achieved' => $achieved,
                'pct' => $pct,
                'status' => match (true) {
                    $target === 0 => 'no-target',
                    $achieved >= $target => 'hit',
                    $pct >= 70 => 'on-track',
                    default => 'behind',
                },
            ];
        });

        return [
            'month' => $anchor->format('Y-m'),
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'target' => $rows->sum('target'),
                'achieved' => $rows->sum('achieved'),
                'pct' => $rows->sum('target') > 0
                    ? round($rows->sum('achieved') / $rows->sum('target') * 100, 1)
                    : null,
            ],
        ];
    }
}
