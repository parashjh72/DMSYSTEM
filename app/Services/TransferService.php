<?php

namespace App\Services;

use App\Jobs\RefreshSummariesJob;
use App\Models\DeviceAudit;
use App\Models\RecordTransfer;
use App\Services\Reporting\DashboardService;
use App\Services\Reporting\FilterOptions;
use App\Support\Imei;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manual reassignment of sales_activation_records to a different retailer
 * (optionally a different distributor). Every run is logged to record_transfers
 * as a dated event, and every moved device gets field-level device_audits rows.
 * A device's original st_date is kept; the move date goes in last_transfer_date.
 */
class TransferService
{
    /** Max affected IMEIs stored verbatim in the audit row. */
    private const IMEI_LOG_CAP = 50_000;

    /**
     * Move a list of IMEIs to the target retailer.
     *
     * @param  list<string>  $rawImeis
     * @param  array{rt_code:string, rt_name?:?string, rd_code?:?string, rd_name?:?string}  $target
     * @param  ?string  $transferDate  effective date (Y-m-d) of the move; defaults to today
     */
    public function byImeis(array $rawImeis, array $target, ?int $userId, ?string $transferDate = null): RecordTransfer
    {
        $imeis = $this->cleanImeis($rawImeis);
        if ($imeis === []) {
            throw new RuntimeException('No valid IMEIs supplied.');
        }
        $this->assertTarget($target);
        $transferDate ??= now()->toDateString();

        return DB::transaction(function () use ($imeis, $target, $userId, $transferDate) {
            $existing = [];
            foreach (array_chunk($imeis, 1000) as $slice) {
                array_push($existing, ...DB::table('sales_activation_records')->whereIn('imei', $slice)->pluck('imei')->all());
            }

            $notFound = array_values(array_diff($imeis, $existing));

            $transfer = RecordTransfer::create([
                'mode' => 'imei_list',
                'transfer_date' => $transferDate,
                'to_rt_code' => $target['rt_code'],
                'to_rt_name' => $target['rt_name'] ?? null,
                'to_rd_code' => $target['rd_code'] ?? null,
                'to_rd_name' => $target['rd_name'] ?? null,
                'move_distributor' => ! empty($target['rd_code']),
                'requested_count' => count($imeis),
                'imeis' => array_slice($existing, 0, self::IMEI_LOG_CAP),
                'not_found' => array_slice($notFound, 0, self::IMEI_LOG_CAP),
                'performed_by' => $userId,
                'created_at' => now(),
            ]);

            $affected = 0;
            foreach (array_chunk($existing, 1000) as $slice) {
                $affected += $this->move(fn (Builder $q) => $q->whereIn('imei', $slice), $target, $transfer, $userId);
            }
            $transfer->update(['affected_count' => $affected]);

            foreach (array_slice($existing, 0, 2_000) as $imei) {
                DeviceTimeline::log($imei, 'transferred',
                    "Transferred to retailer {$target['rt_code']} on {$transferDate}",
                    ['to_rt_code' => $target['rt_code'], 'transfer_date' => $transferDate], $target['rd_code'] ?? null, $target['rt_code'], $userId);
            }

            $this->afterTransfer($target);

            return $transfer;
        });
    }

    /**
     * Move every record (optionally only in-stock / not-activated) from one
     * retailer to another.
     *
     * @param  array{rt_code:string, rt_name?:?string, rd_code?:?string, rd_name?:?string}  $target
     */
    public function byRetailer(string $fromRtCode, array $target, bool $onlyInStock, ?int $userId, ?string $transferDate = null): RecordTransfer
    {
        $fromRtCode = trim($fromRtCode);
        if ($fromRtCode === '') {
            throw new RuntimeException('Choose the retailer to transfer from.');
        }
        $this->assertTarget($target);
        if ($fromRtCode === $target['rt_code']) {
            throw new RuntimeException('Source and target retailer are the same.');
        }
        $transferDate ??= now()->toDateString();

        return DB::transaction(function () use ($fromRtCode, $target, $onlyInStock, $userId, $transferDate) {
            $fromRd = DB::table('sales_activation_records')->where('rt_code', $fromRtCode)->value('rd_code');

            $transfer = RecordTransfer::create([
                'mode' => 'retailer',
                'transfer_date' => $transferDate,
                'only_in_stock' => $onlyInStock,
                'move_distributor' => ! empty($target['rd_code']),
                'from_rt_code' => $fromRtCode,
                'from_rd_code' => $fromRd,
                'to_rt_code' => $target['rt_code'],
                'to_rt_name' => $target['rt_name'] ?? null,
                'to_rd_code' => $target['rd_code'] ?? null,
                'to_rd_name' => $target['rd_name'] ?? null,
                'performed_by' => $userId,
                'created_at' => now(),
            ]);

            $affected = $this->move(
                fn (Builder $q) => $q->where('rt_code', $fromRtCode)->when($onlyInStock, fn ($w) => $w->where('is_activated', 0)),
                $target, $transfer, $userId,
            );
            $transfer->update(['affected_count' => $affected]);

            $this->afterTransfer($target);

            return $transfer;
        });
    }

    /**
     * Audits then applies one transfer to the records selected by $scope. The
     * original st_date is never touched; the move is dated in last_transfer_date.
     *
     * @param  Closure(Builder): Builder  $scope
     * @return int records moved
     */
    private function move(Closure $scope, array $target, RecordTransfer $transfer, ?int $userId): int
    {
        $records = fn (): Builder => $scope(DB::table('sales_activation_records'));
        $columns = ['imei', 'field', 'old_value', 'new_value', 'source', 'record_transfer_id', 'user_id', 'created_at'];

        $audited = ['rt_code' => $target['rt_code']];
        if (! empty($target['rd_code'])) {
            $audited['rd_code'] = $target['rd_code'];
        }
        foreach ($audited as $field => $new) {
            DB::table('device_audits')->insertUsing($columns, $records()
                ->where(fn (Builder $q) => $q->whereNull($field)->orWhere($field, '<>', $new))
                ->selectRaw("imei, ?, {$field}, ?, ?, ?, ?, ?", [
                    $field, $new, DeviceAudit::SOURCE_TRANSFER, $transfer->id, $userId, now()->toDateTimeString(),
                ]));
        }

        return $records()->update($this->assignments($target) + ['last_transfer_date' => $transfer->transfer_date->toDateString()]);
    }

    /** @return array<string,mixed> the SET clause for the update */
    private function assignments(array $target): array
    {
        $set = [
            'rt_code' => $target['rt_code'],
            'updated_at' => now(),
        ];
        if (! empty($target['rt_name'])) {
            $set['rt_name'] = $target['rt_name'];
        }
        if (! empty($target['rd_code'])) {
            $set['rd_code'] = $target['rd_code'];
            if (! empty($target['rd_name'])) {
                $set['rd_name'] = $target['rd_name'];
            }
        }

        return $set;
    }

    private function assertTarget(array $target): void
    {
        if (empty($target['rt_code'])) {
            throw new RuntimeException('Choose a target retailer.');
        }
    }

    /** @param list<string> $raw @return list<string> */
    private function cleanImeis(array $raw): array
    {
        $out = [];
        foreach ($raw as $token) {
            $clean = Imei::clean($token);
            if ($clean !== '' && ! in_array($clean, $out, true)) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /** Keep master data, summaries and filter dropdowns consistent after a bulk update. */
    private function afterTransfer(array $target): void
    {
        // Ensure the target retailer exists as a master row (created_at preserved).
        DB::table('retailers')->insertOrIgnore([
            'code' => $target['rt_code'],
            'name' => $target['rt_name'] ?? null,
            'rd_code' => $target['rd_code'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('retailers')->where('code', $target['rt_code'])->update(array_filter([
            'name' => $target['rt_name'] ?? null,
            'rd_code' => $target['rd_code'] ?? null,
            'updated_at' => now(),
        ], fn ($v) => $v !== null));

        if (! empty($target['rd_code'])) {
            DB::table('retail_distributors')->insertOrIgnore([
                'code' => $target['rd_code'],
                'name' => $target['rd_name'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (! empty($target['rd_name'])) {
                DB::table('retail_distributors')->where('code', $target['rd_code'])
                    ->update(['name' => $target['rd_name'], 'updated_at' => now()]);
            }
        }

        app(FilterOptions::class)->forget();
        app(DashboardService::class)->forget();
        RefreshSummariesJob::dispatch()->onQueue(config('import.queues.summary'));
    }
}
