<?php

namespace App\Services\Reporting;

use App\Models\Scheme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scheme achievement: per retailer, the qualified sell-out value in the scheme
 * period, the slab it lands in, and the payout.
 *
 * Qualified value = sum of each qualifying-model device's effective price
 * (from model_prices, priced on the device's sell-out date) for devices whose
 * sell-out date is inside the scheme window.
 */
class SchemeService
{
    /**
     * @param  bool  $enrolledOnly  restrict to manually-enrolled retailers
     * @return Collection<int, array<string, mixed>>
     */
    public function achievement(Scheme $scheme, ?string $rdCode = null, bool $enrolledOnly = false): Collection
    {
        $basis = $scheme->basisColumn();
        $from = $scheme->effective_from->toDateString();
        $to = $scheme->effective_to->toDateString();

        $modelFilter = match ($scheme->qualified_models) {
            'running', 'out' => "AND r.model IN (SELECT name FROM device_models WHERE status = '{$scheme->qualified_models}')",
            default => '',
        };

        $rows = DB::select("
            WITH price_periods AS (
                SELECT model, price, effective_from,
                    COALESCE(LEAD(effective_from) OVER (PARTITION BY model ORDER BY effective_from), '9999-12-31') AS effective_to
                FROM model_prices
            )
            SELECT
                r.rt_code,
                MAX(r.rt_name) AS rt_name,
                MAX(r.rd_code) AS rd_code,
                MAX(r.rd_name) AS rd_name,
                COUNT(*) AS qty,
                COALESCE(SUM(pp.price), 0) AS qualified_value,
                SUM(pp.price IS NULL) AS unpriced_qty
            FROM sales_activation_records r
            LEFT JOIN price_periods pp
                ON pp.model = r.model
               AND r.{$basis} >= pp.effective_from
               AND r.{$basis} <  pp.effective_to
            WHERE r.{$basis} BETWEEN ? AND ?
              AND r.rt_code IS NOT NULL AND r.rt_code <> ''
              {$modelFilter}
              ".($rdCode ? 'AND r.rd_code = ?' : '').'
            GROUP BY r.rt_code
            ORDER BY qualified_value DESC
        ', $rdCode ? [$from, $to, $rdCode] : [$from, $to]);

        $slabs = $scheme->slabs()->get();
        $enrolled = $scheme->retailers()->get()->keyBy('rt_code');

        $catLabels = collect(config('schemes.categories'))->map(fn ($c) => $c['label']);
        $planLabels = config('schemes.plans');

        $mapped = collect($rows)->map(function ($r) use ($slabs, $enrolled, $catLabels, $planLabels) {
            $value = (float) $r->qualified_value;
            $slab = $slabs->first(fn ($s) => $s->matches($value));
            $pct = $slab ? (float) $slab->payout_percent : 0.0;

            $reg = $enrolled->get($r->rt_code);
            $minSlab = $reg?->effectiveMinSlab();
            $eligible = $reg !== null
                && $slab !== null
                && $slab->slab_no >= $minSlab;

            return [
                'rt_code' => $r->rt_code,
                'rt_name' => $r->rt_name,
                'rd_code' => $r->rd_code,
                'rd_name' => $r->rd_name,
                'qty' => (int) $r->qty,
                'qualified_value' => $value,
                'unpriced_qty' => (int) $r->unpriced_qty,
                'slab_no' => $slab?->slab_no,
                'slab_label' => $slab?->label,
                'payout_percent' => $pct,
                'payout_amount' => round($value * $pct / 100, 2),
                'reward' => $slab?->reward,
                'enrolled' => $reg !== null,
                'plan' => $reg ? ($planLabels[$reg->plan] ?? $reg->plan) : null,
                'plan_key' => $reg?->plan,
                'category' => $reg ? ($catLabels[$reg->category] ?? $reg->category) : null,
                'min_slab' => $minSlab,
                'eligible' => $eligible,
                // what the retailer actually gets under their plan (0 / — if not eligible)
                'entitlement' => match (true) {
                    $reg === null || ! $eligible => null,
                    $reg->plan === 'option_two' => $slab?->reward,
                    default => round($value * $pct / 100, 2),
                },
            ];
        });

        return $enrolledOnly ? $mapped->filter(fn ($r) => $r['enrolled'])->values() : $mapped;
    }
}
