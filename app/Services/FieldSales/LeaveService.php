<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\LeaveRequest;
use App\Models\Pjp;
use App\Models\PjpDay;
use App\Models\User;
use App\Services\PjpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Leave requests: a field user applies, their manager (or an NSM / Admin)
 * approves or rejects. Approval marks the days as leave in the user's PJP and
 * refreshes their attendance days.
 */
class LeaveService
{
    /** How many days back a user may apply for leave (e.g. a sick day taken yesterday). */
    public const BACKDATE_DAYS = 7;

    public function __construct(
        private FieldStaff $staff,
        private AttendanceDayBuilder $days,
        private PjpService $pjps,
    ) {}

    /**
     * @param  array{from_date: string, to_date: string, leave_type: string, half_day?: bool, reason: string}  $data
     */
    public function apply(User $user, array $data): LeaveRequest
    {
        $tz = config('field_sales.timezone');
        $from = Carbon::parse($data['from_date'], $tz)->startOfDay();
        $to = Carbon::parse($data['to_date'], $tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();
        $halfDay = (bool) ($data['half_day'] ?? false);

        if ($to->lt($from)) {
            throw new RuntimeException('The end date must be on or after the start date.');
        }
        if ($from->lt($today->copy()->subDays(self::BACKDATE_DAYS))) {
            throw new RuntimeException('Leave can only be applied up to '.self::BACKDATE_DAYS.' days back.');
        }
        if ($halfDay && ! $from->equalTo($to)) {
            throw new RuntimeException('A half-day leave must start and end on the same day.');
        }

        $overlaps = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString())
            ->exists();
        if ($overlaps) {
            throw new RuntimeException('You already have a pending or approved leave covering these dates.');
        }

        return LeaveRequest::query()->create([
            'user_id' => $user->id,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'leave_type' => $data['leave_type'],
            'half_day' => $halfDay,
            'reason' => trim($data['reason']),
            'status' => 'pending',
        ]);
    }

    public function cancel(LeaveRequest $leave, User $user): void
    {
        if ($leave->user_id !== $user->id) {
            throw new RuntimeException('You can only cancel your own leave.');
        }
        $this->assertPending($leave);

        $leave->update(['status' => 'cancelled']);
    }

    public function approve(LeaveRequest $leave, User $reviewer, ?string $note = null): void
    {
        $this->assertReviewer($leave, $reviewer);
        $this->assertPending($leave);

        DB::transaction(function () use ($leave, $reviewer, $note) {
            $leave->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => trim((string) $note) ?: null,
            ]);

            $this->markPjpDays($leave);
            $this->days->build([$leave->user], $leave->from_date, $leave->to_date);
        });
    }

    public function reject(LeaveRequest $leave, User $reviewer, string $note): void
    {
        $this->assertReviewer($leave, $reviewer);
        $this->assertPending($leave);

        if (trim($note) === '') {
            throw new RuntimeException('Please give a reason for rejecting.');
        }

        $leave->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => trim($note),
        ]);
    }

    private function markPjpDays(LeaveRequest $leave): void
    {
        $pjpIds = Pjp::query()->where('tso_id', $leave->user_id)->pluck('id');
        if ($pjpIds->isEmpty()) {
            return;
        }

        $touched = PjpDay::query()
            ->whereIn('pjp_id', $pjpIds)
            ->whereDate('plan_date', '>=', $leave->from_date->toDateString())
            ->whereDate('plan_date', '<=', $leave->to_date->toDateString())
            ->pluck('pjp_id')
            ->unique();

        PjpDay::query()
            ->whereIn('pjp_id', $pjpIds)
            ->whereDate('plan_date', '>=', $leave->from_date->toDateString())
            ->whereDate('plan_date', '<=', $leave->to_date->toDateString())
            ->update(['day_status' => 'leave', 'updated_at' => now()]);

        Pjp::query()->whereIn('id', $touched)->get()->each(fn (Pjp $pjp) => $this->pjps->recount($pjp));
    }

    private function assertReviewer(LeaveRequest $leave, User $reviewer): void
    {
        if (! $reviewer->can('fs.leave.approve') || $leave->user_id === $reviewer->id
            || ! $this->staff->canSee($reviewer, $leave->user)) {
            throw new RuntimeException('You are not allowed to review this leave request.');
        }
    }

    private function assertPending(LeaveRequest $leave): void
    {
        if (! $leave->isPending()) {
            throw new RuntimeException('This leave request has already been '.$leave->status.'.');
        }
    }
}
