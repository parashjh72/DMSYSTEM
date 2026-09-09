<?php

namespace App\Services;

use App\Mail\PjpWorkflowMail;
use App\Models\Pjp;
use App\Models\PjpDay;
use App\Models\PjpEvent;
use App\Models\PjpVisit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The PJP lifecycle. Every transition is transaction-guarded, asserts the source
 * status, writes a PjpEvent, and best-effort emails the next actor. ASM approval
 * is NOT final — only nsmFinalApprove() locks the plan.
 */
class PjpService
{
    /** Create (or return) the TSO's PJP for a month, one PjpDay per calendar date. */
    public function openMonth(User $tso, int $year, int $month): Pjp
    {
        $pjp = Pjp::firstOrNew(['tso_id' => $tso->id, 'year' => $year, 'month' => $month]);

        if (! $pjp->exists) {
            $pjp->fill([
                'uuid' => (string) Str::uuid(),
                'status' => 'draft',
                'asm_id' => $tso->resolveAsm()?->id,
                'nsm_id' => $tso->resolveNsm()?->id,
            ])->save();

            $start = Carbon::create($year, $month, 1);
            $rows = [];
            for ($d = $start->copy(); $d->month === $month; $d->addDay()) {
                $rows[] = [
                    'pjp_id' => $pjp->id, 'plan_date' => $d->toDateString(),
                    'day_status' => 'no_plan', 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            PjpDay::insert($rows);

            $this->event($pjp, 'created', null, 'draft', $tso, 'TSO');
        }

        return $pjp;
    }

    /**
     * @param  list<string>  $rtCodes
     */
    public function saveDay(PjpDay $day, string $status, ?string $notes, array $rtCodes): void
    {
        $pjp = $day->pjp;
        if (! $pjp->isEditableByTso()) {
            throw new RuntimeException('This plan can no longer be edited.');
        }

        DB::transaction(function () use ($day, $status, $notes, $rtCodes) {
            $day->update([
                'day_status' => array_key_exists($status, config('pjp.day_statuses')) ? $status : 'no_plan',
                'notes' => trim((string) $notes) ?: null,
            ]);

            $codes = array_values(array_unique(array_filter(array_map('trim', $rtCodes))));
            $day->retailers()->whereNotIn('rt_code', $codes ?: ['__none__'])->delete();

            $names = DB::table('retailers')->whereIn('code', $codes)->pluck('name', 'code');
            foreach ($codes as $code) {
                $day->retailers()->updateOrCreate(['rt_code' => $code], ['rt_name' => $names[$code] ?? null]);
            }
        });

        $this->recount($pjp->refresh());
    }

    public function submit(Pjp $pjp, User $tso): void
    {
        $this->assertStatus($pjp, ['draft', 'revision_required', 'resubmitted']);

        if ($pjp->days()->where('day_status', 'planned')->doesntExist()) {
            throw new RuntimeException('Plan at least one day before submitting.');
        }

        $from = $pjp->status;
        DB::transaction(function () use ($pjp, $tso, $from) {
            $this->recount($pjp);
            $pjp->update([
                'status' => 'submitted',
                'asm_id' => $pjp->asm_id ?: $tso->resolveAsm()?->id,
                'nsm_id' => $pjp->nsm_id ?: $tso->resolveNsm()?->id,
                'submitted_at' => now(),
            ]);
            $this->event($pjp, 'submitted', $from, 'submitted', $tso, 'TSO');
        });

        $this->notify($pjp, $pjp->asm, "New PJP submitted by {$tso->name} for {$pjp->monthLabel()} — awaiting your review.");
    }

    public function asmRequestRevision(Pjp $pjp, User $asm, string $comment): void
    {
        $this->assertStatus($pjp, ['submitted', 'resubmitted', 'asm_review']);
        $this->revision($pjp, $asm, 'ASM', 'asm_request_revision', $comment);
        $this->notify($pjp, $pjp->tso, "Your {$pjp->monthLabel()} PJP requires revision (ASM): {$comment}");
    }

    public function asmApprove(Pjp $pjp, User $asm, ?string $comment): void
    {
        $this->assertStatus($pjp, ['submitted', 'resubmitted', 'asm_review']);

        $from = $pjp->status;
        DB::transaction(function () use ($pjp, $asm, $from, $comment) {
            $pjp->update([
                'status' => 'forwarded_to_nsm',
                'asm_id' => $asm->id,
                'asm_reviewed_at' => now(),
                'asm_approved_at' => now(),
                'forwarded_to_nsm_at' => now(),
                'nsm_id' => $pjp->nsm_id ?: $asm->resolveNsm()?->id,
            ]);
            $this->event($pjp, 'asm_approved', $from, 'forwarded_to_nsm', $asm, 'ASM', $comment);
        });

        $this->notify($pjp, $pjp->nsm, "PJP for {$pjp->tso?->name} ({$pjp->monthLabel()}) approved by ASM — awaiting your final approval.");
        $this->notify($pjp, $pjp->tso, "Your {$pjp->monthLabel()} PJP has been approved by ASM and forwarded to NSM.");
    }

    public function nsmRequestRevision(Pjp $pjp, User $nsm, string $comment): void
    {
        $this->assertStatus($pjp, ['forwarded_to_nsm', 'nsm_review']);
        $this->revision($pjp, $nsm, 'NSM', 'nsm_request_revision', $comment);
        $this->notify($pjp, $pjp->tso, "Your {$pjp->monthLabel()} PJP requires revision (NSM): {$comment}");
        $this->notify($pjp, $pjp->asm, "PJP for {$pjp->tso?->name} ({$pjp->monthLabel()}) was returned for revision by NSM.");
    }

    public function nsmReject(Pjp $pjp, User $nsm, ?string $comment): void
    {
        $this->assertStatus($pjp, ['forwarded_to_nsm', 'nsm_review']);

        $from = $pjp->status;
        DB::transaction(function () use ($pjp, $nsm, $from, $comment) {
            $pjp->update(['status' => 'rejected', 'nsm_id' => $nsm->id, 'nsm_reviewed_at' => now()]);
            $this->event($pjp, 'nsm_rejected', $from, 'rejected', $nsm, 'NSM', $comment);
        });

        $this->notify($pjp, $pjp->tso, "Your {$pjp->monthLabel()} PJP was rejected by NSM.".($comment ? " Reason: {$comment}" : ''));
    }

    public function nsmFinalApprove(Pjp $pjp, User $nsm, ?string $comment): void
    {
        $this->assertStatus($pjp, ['forwarded_to_nsm', 'nsm_review']);

        $from = $pjp->status;
        DB::transaction(function () use ($pjp, $nsm, $from, $comment) {
            $pjp->update([
                'status' => 'final_approved',
                'nsm_id' => $nsm->id,
                'nsm_reviewed_at' => now(),
                'final_approved_at' => now(),
                'final_approved_by' => $nsm->id,
                'locked_at' => now(),
            ]);
            $this->event($pjp, 'nsm_final_approved', $from, 'final_approved', $nsm, 'NSM', $comment);
        });

        $this->notify($pjp, $pjp->tso, "Your {$pjp->monthLabel()} PJP has received final approval.");
        $this->notify($pjp, $pjp->asm, "PJP for {$pjp->tso?->name} ({$pjp->monthLabel()}) has been final-approved by NSM.");
    }

    /** TSO logs an actual visit against a planned day. */
    public function logVisit(User $tso, PjpDay $day, string $rtCode, array $gps, ?string $note = null): PjpVisit
    {
        if ($day->pjp->tso_id !== $tso->id) {
            abort(403);
        }

        return PjpVisit::updateOrCreate(
            ['pjp_day_id' => $day->id, 'rt_code' => $rtCode],
            [
                'pjp_id' => $day->pjp_id,
                'user_id' => $tso->id,
                'visited_at' => now(),
                'latitude' => $gps['latitude'] ?? null,
                'longitude' => $gps['longitude'] ?? null,
                'accuracy' => $gps['accuracy'] ?? null,
                'note' => $note,
            ],
        );
    }

    // ---------------------------------------------------------------

    private function revision(Pjp $pjp, User $actor, string $role, string $action, string $comment): void
    {
        $from = $pjp->status;
        DB::transaction(function () use ($pjp, $actor, $role, $action, $from, $comment) {
            $pjp->update([
                'status' => 'revision_required',
                'revision_count' => $pjp->revision_count + 1,
                ...($role === 'ASM' ? ['asm_reviewed_at' => now()] : ['nsm_reviewed_at' => now()]),
            ]);
            $this->event($pjp, $action, $from, 'revision_required', $actor, $role, $comment);
        });
    }

    public function recount(Pjp $pjp): void
    {
        $days = $pjp->days()->where('day_status', 'planned')->withCount('retailers')->get();
        $pjp->update([
            'planned_days' => $days->count(),
            'planned_visits' => $days->sum('retailers_count'),
        ]);
    }

    /** @param list<string> $expected */
    private function assertStatus(Pjp $pjp, array $expected): void
    {
        if (! in_array($pjp->status, $expected, true)) {
            throw new RuntimeException('This PJP is no longer at that stage ('.$pjp->statusLabel().').');
        }
    }

    private function event(Pjp $pjp, string $action, ?string $from, ?string $to, ?User $actor, ?string $role, ?string $comment = null): void
    {
        PjpEvent::create([
            'pjp_id' => $pjp->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actor?->id, 'actor_role' => $role,
            'comment' => $comment ? mb_substr($comment, 0, 2000) : null,
            'created_at' => now(),
        ]);
    }

    private function notify(Pjp $pjp, ?User $to, string $message): void
    {
        if (! $to?->email) {
            return;
        }

        try {
            Mail::to($to->email)->send(new PjpWorkflowMail($pjp, $message));
        } catch (Throwable) {
            // email is best-effort; the in-app status/queue is the source of truth
        }
    }
}
