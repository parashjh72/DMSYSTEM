<?php

namespace App\Services\Reporting;

use App\Models\ModelPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Effective-dated model pricing and the value reports built on it.
 */
class PriceService
{
    /**
     * SQL for the price periods: each price row with a derived end date
     * (the next row's effective_from, or the far future).
     */
    private function periodsCte(): string
    {
        return "WITH price_periods AS (
            SELECT model, price, effective_from,
                COALESCE(
                    LEAD(effective_from) OVER (PARTITION BY model ORDER BY effective_from),
                    '9999-12-31'
                ) AS effective_to
            FROM model_prices
        )";
    }

    /** All price rows for one model, oldest first. */
    public function history(string $model): Collection
    {
        return ModelPrice::where('model', $model)->orderBy('effective_from')->get();
    }

    /** model => current price (the row effective as of today). */
    public function currentMap(): array
    {
        $rows = DB::select("
            {$this->periodsCte()}
            SELECT model, price
            FROM price_periods
            WHERE effective_from <= CURDATE() AND effective_to > CURDATE()
        ");

        return collect($rows)->pluck('price', 'model')->map(fn ($p) => (float) $p)->all();
    }

    public function set(string $model, float $price, string $effectiveFrom, ?string $note, ?int $userId): ModelPrice
    {
        return ModelPrice::updateOrCreate(
            ['model' => $model, 'effective_from' => $effectiveFrom],
            ['price' => $price, 'note' => $note, 'created_by' => $userId],
        );
    }

    /**
     * Model-wise value of devices whose $basis date falls in [$from, $to].
     * Each device is priced by the period in force on its $basis date, so a
     * range that spans a price change blends the rates automatically.
     *
     * @param  'activation_date'|'st_date'  $basis
     * @return Collection<int, array<string, mixed>>
     */
    public function valueByModel(?string $from, ?string $to, string $basis, ?string $rdCode): Collection
    {
        $basis = $basis === 'st_date' ? 'st_date' : 'activation_date';
        $from ??= '1900-01-01';
        $to ??= '9999-12-30';

        $rows = DB::select("
            {$this->periodsCte()}
            SELECT
                r.model,
                COUNT(*) AS qty,
                COALESCE(SUM(pp.price), 0) AS total_value,
                SUM(pp.price IS NULL) AS unpriced_qty,
                MIN(pp.price) AS min_price,
                MAX(pp.price) AS max_price
            FROM sales_activation_records r
            LEFT JOIN price_periods pp
                ON pp.model = r.model
               AND r.{$basis} >= pp.effective_from
               AND r.{$basis} <  pp.effective_to
            WHERE r.{$basis} BETWEEN ? AND ?
              ".($rdCode ? 'AND r.rd_code = ?' : '').'
            GROUP BY r.model
            ORDER BY total_value DESC
        ', $rdCode ? [$from, $to, $rdCode] : [$from, $to]);

        return collect($rows)->map(fn ($r) => [
            'model' => $r->model,
            'qty' => (int) $r->qty,
            'total_value' => (float) $r->total_value,
            'unpriced_qty' => (int) $r->unpriced_qty,
            'avg_price' => $r->qty > 0 ? round($r->total_value / max(1, $r->qty - $r->unpriced_qty), 2) : 0,
            'price_range' => $r->min_price !== null && $r->min_price != $r->max_price
                ? number_format((float) $r->min_price, 2).' – '.number_format((float) $r->max_price, 2)
                : ($r->min_price !== null ? number_format((float) $r->min_price, 2) : '—'),
        ]);
    }
}
