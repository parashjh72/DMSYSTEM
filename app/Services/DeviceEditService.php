<?php

namespace App\Services;

use App\Jobs\RefreshSummariesJob;
use App\Models\DeviceAudit;
use App\Models\SalesActivationRecord;
use App\Models\User;
use App\Services\Import\ImportDateGuard;
use App\Services\Reporting\DashboardService;
use App\Services\Reporting\FilterOptions;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hand-correction of one device (admin only). Every changed field is written to
 * device_audits with the editor and their reason; the same date rules as the
 * import apply (activation >= ST >= sell-in).
 */
class DeviceEditService
{
    /** Fields an admin may edit by hand. */
    public const EDITABLE = ['tso', 'rd_code', 'rt_code', 'st_date', 'activation_date'];

    /**
     * @param  array<string, ?string>  $values  new values keyed by EDITABLE field; '' or null clears
     * @return int number of fields changed
     */
    public function update(SalesActivationRecord $record, array $values, string $reason, User $user): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Please give a reason for this change.');
        }

        $changes = [];
        foreach (self::EDITABLE as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }
            $new = trim((string) $values[$field]) === '' ? null : trim((string) $values[$field]);
            $old = $this->stored($record, $field);
            if ($new !== $old) {
                $changes[$field] = [$old, $new];
            }
        }

        if ($changes === []) {
            return 0;
        }

        $stDate = array_key_exists('st_date', $changes) ? $changes['st_date'][1] : $this->stored($record, 'st_date');
        $activationDate = array_key_exists('activation_date', $changes) ? $changes['activation_date'][1] : $this->stored($record, 'activation_date');
        $sellInDate = $this->stored($record, 'sell_in_date');
        if (($activationDate !== null && $stDate !== null && $activationDate < $stDate)
            || ($stDate !== null && $sellInDate !== null && $stDate < $sellInDate)) {
            throw new RuntimeException(ImportDateGuard::describe($stDate, $activationDate, $sellInDate));
        }

        DB::transaction(function () use ($record, $changes, $reason, $user) {
            $set = ['updated_at' => now()];
            foreach ($changes as $field => [, $new]) {
                $set[$field] = $new;
            }
            if (array_key_exists('rt_code', $changes)) {
                $set['rt_name'] = $changes['rt_code'][1] === null ? null
                    : DB::table('retailers')->where('code', $changes['rt_code'][1])->value('name');
            }
            if (array_key_exists('rd_code', $changes)) {
                $set['rd_name'] = $changes['rd_code'][1] === null ? null
                    : DB::table('retail_distributors')->where('code', $changes['rd_code'][1])->value('name');
            }

            DB::table('sales_activation_records')->where('id', $record->id)->update($set);

            foreach ($changes as $field => [$old, $new]) {
                DeviceAudit::create([
                    'imei' => $record->imei,
                    'field' => $field,
                    'old_value' => $old,
                    'new_value' => $new,
                    'source' => DeviceAudit::SOURCE_MANUAL,
                    'user_id' => $user->id,
                    'reason' => mb_substr($reason, 0, 500),
                    'created_at' => now(),
                ]);
            }
        });

        app(FilterOptions::class)->forget();
        app(DashboardService::class)->forget();
        RefreshSummariesJob::dispatch()->onQueue(config('import.queues.summary'));

        return count($changes);
    }

    private function stored(SalesActivationRecord $record, string $field): ?string
    {
        $value = $record->getRawOriginal($field);
        if ($value === null || $value === '') {
            return null;
        }

        // Date columns come back as 'Y-m-d' (MySQL) or 'Y-m-d 00:00:00' (SQLite).
        return in_array($field, ['st_date', 'activation_date', 'sell_in_date'], true)
            ? substr((string) $value, 0, 10)
            : (string) $value;
    }
}
