<?php

namespace App\Services;

use App\Models\Promoter;
use App\Models\PromoterRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TSO-raised promoter (RA) requests and their two-step approval:
 * TSO submit -> ASM -> NSM -> Promoter created.
 */
class PromoterRequestService
{
    /**
     * The retailer's device counts for the last N whole calendar months.
     *
     * @return list<array{month: string, label: string, activations: int, sell_through: int}>
     */
    public function salesPreview(string $rtCode, int $months = 3): array
    {
        $rtCode = trim($rtCode);
        if ($rtCode === '') {
            return [];
        }

        $tz = config('reports.timezone', 'Asia/Kathmandu');
        $start = Carbon::now($tz)->startOfMonth()->subMonths($months);
        $end = Carbon::now($tz)->startOfMonth()->subDay(); // last day of previous month

        $act = DB::table('sales_activation_records')
            ->selectRaw("DATE_FORMAT(activation_date, '%Y-%m') ym, COUNT(*) c")
            ->where('rt_code', $rtCode)->where('is_activated', 1)
            ->whereBetween('activation_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym')->pluck('c', 'ym');

        $sell = DB::table('sales_activation_records')
            ->selectRaw("DATE_FORMAT(st_date, '%Y-%m') ym, COUNT(*) c")
            ->where('rt_code', $rtCode)->whereNotNull('st_date')
            ->whereBetween('st_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym')->pluck('c', 'ym');

        $out = [];
        for ($i = $months; $i >= 1; $i--) {
            $m = Carbon::now($tz)->startOfMonth()->subMonths($i);
            $ym = $m->format('Y-m');
            $out[] = [
                'month' => $ym,
                'label' => $m->format('M Y'),
                'activations' => (int) ($act[$ym] ?? 0),
                'sell_through' => (int) ($sell[$ym] ?? 0),
            ];
        }

        return $out;
    }

    public function create(User $tso, array $data): PromoterRequest
    {
        $rt = DB::table('retailers')->where('code', $data['rt_code'])->first();
        if (! $rt) {
            throw new RuntimeException('Pick a retailer from the list.');
        }

        $scope = $tso->scopedRdCodes();
        if ($scope !== [] && ! in_array($rt->rd_code, $scope, true)) {
            throw new RuntimeException('That retailer is not in your territory.');
        }

        if (! array_key_exists($data['type'], config('promoters.types'))) {
            throw new RuntimeException('Choose an RA type.');
        }

        return PromoterRequest::create([
            'type' => $data['type'],
            'rt_code' => $rt->code,
            'rt_name' => $rt->name,
            'rd_code' => $rt->rd_code,
            'promoter_name' => trim($data['promoter_name'] ?? '') ?: null,
            'proposed_target' => (int) ($data['proposed_target'] ?? 0),
            'sales_snapshot' => ['months' => $this->salesPreview($rt->code)],
            'note' => trim($data['note'] ?? '') ?: null,
            'status' => 'pending_asm',
            'requested_by' => $tso->id,
        ]);
    }

    public function approveAsm(PromoterRequest $request, User $asm, ?string $note): void
    {
        $this->assertStatus($request, 'pending_asm');

        $request->update([
            'status' => 'pending_nsm',
            'asm_by' => $asm->id,
            'asm_at' => now(),
            'asm_note' => $note,
        ]);
    }

    public function approveNsm(PromoterRequest $request, User $nsm, ?string $note): void
    {
        $this->assertStatus($request, 'pending_nsm');

        DB::transaction(function () use ($request, $nsm, $note) {
            $promoter = Promoter::create([
                'name' => $request->promoter_name ?: 'RA — '.($request->rt_name ?: $request->rt_code),
                'type' => $request->type,
                'rt_code' => $request->rt_code,
                'rt_name' => $request->rt_name,
                'rd_code' => $request->rd_code,
                'monthly_target' => $request->proposed_target,
                'active' => true,
                'created_by' => $nsm->id,
            ]);

            $request->update([
                'status' => 'approved',
                'nsm_by' => $nsm->id,
                'nsm_at' => now(),
                'nsm_note' => $note,
                'promoter_id' => $promoter->id,
            ]);
        });
    }

    public function reject(PromoterRequest $request, User $reviewer, string $stage, ?string $note): void
    {
        $expected = $stage === 'asm' ? 'pending_asm' : 'pending_nsm';
        $this->assertStatus($request, $expected);

        $request->update([
            'status' => 'rejected',
            'rejected_stage' => $stage,
            ...($stage === 'asm'
                ? ['asm_by' => $reviewer->id, 'asm_at' => now(), 'asm_note' => $note]
                : ['nsm_by' => $reviewer->id, 'nsm_at' => now(), 'nsm_note' => $note]),
        ]);
    }

    private function assertStatus(PromoterRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw new RuntimeException('This request is no longer at that stage.');
        }
    }
}
