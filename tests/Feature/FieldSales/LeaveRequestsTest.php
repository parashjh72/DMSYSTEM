<?php

namespace Tests\Feature\FieldSales;

use App\Livewire\FieldSales\LeaveRequests;
use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\LeaveRequest;
use App\Models\Pjp;
use App\Models\PjpDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveRequestsTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $asm;

    private User $tso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->asm = $this->makeUser('ASM', ['RD001']);
        $this->tso = $this->makeUser('TSO', ['RD001'], $this->asm);
    }

    private function apply(array $overrides = [])
    {
        return Livewire::actingAs($this->tso)->test(LeaveRequests::class)
            ->set('fromDate', $overrides['fromDate'] ?? '2026-09-28')
            ->set('toDate', $overrides['toDate'] ?? '2026-09-29')
            ->set('leaveType', 'sick')
            ->set('halfDay', $overrides['halfDay'] ?? false)
            ->set('reason', $overrides['reason'] ?? 'Fever')
            ->call('apply');
    }

    public function test_field_officer_applies_for_leave(): void
    {
        $this->apply()->assertSet('error', null)->assertSet('flash', 'Leave request sent to your manager.');

        $leave = LeaveRequest::query()->sole();
        $this->assertSame($this->tso->id, $leave->user_id);
        $this->assertSame('pending', $leave->status);
        $this->assertSame(2, $leave->days());
    }

    public function test_reason_is_required(): void
    {
        $this->apply(['reason' => ''])->assertHasErrors(['reason' => 'required']);

        $this->assertDatabaseCount('fs_leave_requests', 0);
    }

    public function test_overlapping_leave_is_refused(): void
    {
        $this->apply();
        $this->apply(['fromDate' => '2026-09-29', 'toDate' => '2026-09-30'])
            ->assertSet('error', 'You already have a pending or approved leave covering these dates.');

        $this->assertDatabaseCount('fs_leave_requests', 1);
    }

    public function test_leave_more_than_a_week_back_is_refused(): void
    {
        $this->apply(['fromDate' => '2026-09-10', 'toDate' => '2026-09-10'])
            ->assertSet('error', 'Leave can only be applied up to 7 days back.');
    }

    public function test_half_day_must_be_a_single_day(): void
    {
        $this->apply(['halfDay' => true])
            ->assertSet('error', 'A half-day leave must start and end on the same day.');
    }

    public function test_manager_approval_marks_pjp_days_and_attendance_as_leave(): void
    {
        $pjp = Pjp::query()->create(['uuid' => (string) Str::uuid(), 'tso_id' => $this->tso->id, 'year' => 2026, 'month' => 9, 'status' => 'approved']);
        foreach (['2026-09-24', '2026-09-28'] as $date) {
            PjpDay::query()->create(['pjp_id' => $pjp->id, 'plan_date' => $date, 'day_status' => 'planned']);
        }
        $leave = LeaveRequest::factory()->create(['user_id' => $this->tso->id, 'from_date' => '2026-09-24', 'to_date' => '2026-09-28']);

        Livewire::actingAs($this->asm)->test(LeaveRequests::class)
            ->set("notes.{$leave->id}", 'Get well soon')
            ->call('approve', $leave->id)
            ->assertSet('error', null);

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($this->asm->id, $leave->reviewed_by);
        $this->assertSame('Get well soon', $leave->review_note);
        $this->assertSame(['leave', 'leave'], PjpDay::query()->orderBy('plan_date')->pluck('day_status')->all());
        $this->assertSame(0, $pjp->refresh()->planned_days);
        $this->assertSame('leave', AttendanceDay::query()->whereDate('attendance_date', '2026-09-24')->value('status'));
    }

    public function test_manager_cannot_review_someone_outside_their_team(): void
    {
        $outsider = $this->makeUser('TSO', ['RD002']);
        $leave = LeaveRequest::factory()->create(['user_id' => $outsider->id]);

        Livewire::actingAs($this->asm)->test(LeaveRequests::class)
            ->call('approve', $leave->id)
            ->assertSet('error', 'You are not allowed to review this leave request.');

        $this->assertSame('pending', $leave->refresh()->status);
    }

    public function test_field_officer_cannot_approve_leave(): void
    {
        $colleague = $this->makeUser('TSO', ['RD001'], $this->asm);
        $leave = LeaveRequest::factory()->create(['user_id' => $colleague->id]);

        Livewire::actingAs($this->tso)->test(LeaveRequests::class)
            ->call('approve', $leave->id)
            ->assertSet('error', 'You are not allowed to review this leave request.');

        $this->assertSame('pending', $leave->refresh()->status);
    }

    public function test_rejection_needs_a_note(): void
    {
        $leave = LeaveRequest::factory()->create(['user_id' => $this->tso->id]);

        Livewire::actingAs($this->asm)->test(LeaveRequests::class)
            ->call('reject', $leave->id)
            ->assertSet('error', 'Please give a reason for rejecting.')
            ->set("notes.{$leave->id}", 'Month-end closing')
            ->call('reject', $leave->id)
            ->assertSet('error', null);

        $this->assertSame('rejected', $leave->refresh()->status);
    }

    public function test_officer_can_cancel_only_their_own_pending_leave(): void
    {
        $mine = LeaveRequest::factory()->create(['user_id' => $this->tso->id]);
        $theirs = LeaveRequest::factory()->create(['user_id' => $this->makeUser('TSO')->id]);

        Livewire::actingAs($this->tso)->test(LeaveRequests::class)
            ->call('cancel', $theirs->id)
            ->assertSet('error', 'You can only cancel your own leave.')
            ->call('cancel', $mine->id)
            ->assertSet('error', null);

        $this->assertSame('cancelled', $mine->refresh()->status);
        $this->assertSame('pending', $theirs->refresh()->status);
    }

    public function test_leave_page_is_closed_to_roles_without_leave_permissions(): void
    {
        $this->actingAs($this->makeUser('RD', ['RD001']))->get(route('field-sales.leave'))->assertForbidden();
        $this->actingAs($this->tso)->get(route('field-sales.leave'))->assertOk()->assertSee('Apply for leave');
        $this->actingAs($this->asm)->get(route('field-sales.leave'))->assertOk()->assertSee('Team requests');
    }
}
