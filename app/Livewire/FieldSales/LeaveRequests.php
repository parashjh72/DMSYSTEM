<?php

namespace App\Livewire\FieldSales;

use App\Models\FieldSales\LeaveRequest;
use App\Services\FieldSales\FieldStaff;
use App\Services\FieldSales\LeaveService;
use App\Support\NepaliDate;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Leave')]
class LeaveRequests extends Component
{
    use WithPagination;

    public string $fromDate = '';

    public string $toDate = '';

    public string $leaveType = 'casual';

    public bool $halfDay = false;

    public string $reason = '';

    /** Review notes keyed by leave request id. */
    public array $notes = [];

    public string $statusFilter = 'pending';

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('field-sales.leave.access'), 403);
        $this->fromDate = $this->toDate = Carbon::now(config('field_sales.timezone'))->toDateString();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function apply(LeaveService $service): void
    {
        abort_unless(auth()->user()?->can('fs.leave.request'), 403);
        $this->reset('flash', 'error');

        $data = $this->validate([
            'fromDate' => ['required', 'date'],
            'toDate' => ['required', 'date'],
            'leaveType' => ['required', 'in:'.implode(',', array_keys(LeaveRequest::TYPES))],
            'halfDay' => ['boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [], ['fromDate' => 'from date', 'toDate' => 'to date', 'leaveType' => 'leave type']);

        try {
            $service->apply(auth()->user(), [
                'from_date' => $data['fromDate'],
                'to_date' => $data['toDate'],
                'leave_type' => $data['leaveType'],
                'half_day' => $data['halfDay'],
                'reason' => $data['reason'],
            ]);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('reason', 'halfDay');
        $this->flash = 'Leave request sent to your manager.';
    }

    public function cancel(LeaveService $service, int $id): void
    {
        $this->act(fn () => $service->cancel(LeaveRequest::findOrFail($id), auth()->user()), 'Leave request cancelled.');
    }

    public function approve(LeaveService $service, int $id): void
    {
        $this->act(fn () => $service->approve(LeaveRequest::findOrFail($id), auth()->user(), $this->notes[$id] ?? null), 'Leave approved.');
    }

    public function reject(LeaveService $service, int $id): void
    {
        $this->act(fn () => $service->reject(LeaveRequest::findOrFail($id), auth()->user(), (string) ($this->notes[$id] ?? '')), 'Leave rejected.');
    }

    private function act(callable $action, string $message): void
    {
        $this->reset('flash', 'error');

        try {
            $action();
            $this->flash = $message;
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(FieldStaff $staff)
    {
        $user = auth()->user();
        $canReview = $user->can('fs.leave.approve');
        $visible = $canReview ? $staff->visibleUserIds($user) : null;

        return view('livewire.field-sales.leave-requests', [
            'canApply' => $user->can('fs.leave.request'),
            'canReview' => $canReview,
            'mine' => $user->can('fs.leave.request')
                ? LeaveRequest::query()->where('user_id', $user->id)->latest()->take(10)->get()
                : collect(),
            'queue' => $canReview
                ? LeaveRequest::query()
                    ->with('user', 'reviewer')
                    ->where('user_id', '!=', $user->id)
                    ->when($visible !== null, fn ($q) => $q->whereIn('user_id', $visible))
                    ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                    ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->latest()
                    ->paginate(20)
                : null,
            'types' => LeaveRequest::TYPES,
            'bs' => fn ($date) => NepaliDate::format($date),
        ]);
    }
}
