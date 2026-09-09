<?php

namespace App\Services;

use App\Jobs\RefreshSummariesJob;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Reporting\DashboardService;
use App\Services\Reporting\FilterOptions;
use App\Support\Imei;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RD-initiated device returns. An RD login submits IMEIs currently sitting at a
 * retailer; Admin / Super Admin approves; on approval the retailer + invoice
 * date are cleared and the device drops back to unassigned stock under the same
 * distributor. Every step is written to the device timeline.
 */
class ReturnService
{
    private const IMEI_CAP = 20_000;

    /**
     * Create a pending return request from a pasted IMEI list, limited to the
     * requester's own distributor codes.
     *
     * @param  list<string>  $rawImeis
     * @return array{request: ?ReturnRequest, rejected: array<string,string>}
     */
    public function request(array $rawImeis, ?string $note, User $requester): array
    {
        $imeis = $this->cleanImeis($rawImeis);
        if ($imeis === []) {
            throw new RuntimeException('No valid IMEIs supplied.');
        }

        $scope = $requester->scopedRdCodes();
        if ($scope === []) {
            throw new RuntimeException('Your account is not linked to any distributor.');
        }

        $records = DB::table('sales_activation_records')
            ->whereIn('imei', $imeis)
            ->get(['imei', 'rd_code', 'rt_code'])
            ->keyBy('imei');

        $pending = ReturnRequest::query()
            ->where('status', 'pending')
            ->get('imeis')
            ->flatMap(fn ($r) => $r->imeis ?? [])
            ->flip();

        $valid = [];
        $rejected = [];

        foreach ($imeis as $imei) {
            $row = $records->get($imei);
            $rejected[$imei] = match (true) {
                $row === null => 'not in the system',
                ! in_array($row->rd_code, $scope, true) => 'belongs to another distributor',
                (string) $row->rt_code === '' => 'not currently at a retailer',
                $pending->has($imei) => 'already has a pending return request',
                default => null,
            };

            if ($rejected[$imei] === null) {
                unset($rejected[$imei]);
                $valid[] = ['imei' => $imei, 'rd_code' => $row->rd_code];
            }
        }

        if ($valid === []) {
            return ['request' => null, 'rejected' => $rejected];
        }

        // One request per distributor code (usually just one).
        $byRd = collect($valid)->groupBy('rd_code');
        $request = null;

        DB::transaction(function () use ($byRd, $note, $requester, $rejected, $records, &$request) {
            foreach ($byRd as $rdCode => $items) {
                $imeis = $items->pluck('imei')->all();
                $request = ReturnRequest::create([
                    'rd_code' => $rdCode,
                    'imeis' => $imeis,
                    'rejected' => $rejected ?: null,
                    'requested_count' => count($imeis),
                    'status' => 'pending',
                    'note' => $note,
                    'requested_by' => $requester->id,
                ]);

                foreach ($imeis as $imei) {
                    $fromRt = (string) ($records->get($imei)->rt_code ?? '');
                    DeviceTimeline::log($imei, 'return_requested',
                        "Return requested by {$requester->name}",
                        ['request_uuid' => $request->uuid, 'from_rt_code' => $fromRt],
                        $rdCode, $fromRt ?: null, $requester->id);
                }
            }
        });

        return ['request' => $request, 'rejected' => $rejected];
    }

    public function approve(ReturnRequest $request, ?string $note, User $reviewer): void
    {
        if ($request->status !== 'pending') {
            throw new RuntimeException('This request has already been reviewed.');
        }

        DB::transaction(function () use ($request, $note, $reviewer) {
            // Only devices still under the same distributor and still at a retailer.
            $rows = DB::table('sales_activation_records')
                ->whereIn('imei', $request->imeis)
                ->where('rd_code', $request->rd_code)
                ->whereNotNull('rt_code')->where('rt_code', '<>', '')
                ->get(['imei', 'rt_code', 'rt_name', 'st_date']);

            if ($rows->isNotEmpty()) {
                DB::table('sales_activation_records')
                    ->whereIn('imei', $rows->pluck('imei')->all())
                    ->update(['rt_code' => null, 'rt_name' => null, 'st_date' => null, 'updated_at' => now()]);
            }

            foreach ($rows as $row) {
                DeviceTimeline::log($row->imei, 'return_approved',
                    "Return approved by {$reviewer->name} — back to {$request->rd_code} stock",
                    ['from_rt_code' => $row->rt_code, 'from_rt_name' => $row->rt_name,
                        'from_st_date' => $row->st_date, 'request_uuid' => $request->uuid],
                    $request->rd_code, $row->rt_code, $reviewer->id);
            }

            $request->update([
                'status' => 'approved',
                'approved_count' => $rows->count(),
                'review_note' => $note,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });

        $this->afterChange();
    }

    public function reject(ReturnRequest $request, ?string $note, User $reviewer): void
    {
        if ($request->status !== 'pending') {
            throw new RuntimeException('This request has already been reviewed.');
        }

        $request->update([
            'status' => 'rejected',
            'review_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        foreach ($request->imeis as $imei) {
            DeviceTimeline::log($imei, 'return_rejected',
                "Return rejected by {$reviewer->name}",
                ['request_uuid' => $request->uuid], $request->rd_code, null, $reviewer->id);
        }
    }

    private function afterChange(): void
    {
        app(FilterOptions::class)->forget();
        app(DashboardService::class)->forget();
        RefreshSummariesJob::dispatch()->onQueue(config('import.queues.summary'));
    }

    /**
     * @param  list<string>  $raw
     * @return list<string>
     */
    private function cleanImeis(array $raw): array
    {
        $out = [];
        foreach ($raw as $token) {
            $clean = Imei::clean($token);
            if ($clean !== '' && ! in_array($clean, $out, true)) {
                $out[] = $clean;
            }
        }

        return array_slice($out, 0, self::IMEI_CAP);
    }
}
