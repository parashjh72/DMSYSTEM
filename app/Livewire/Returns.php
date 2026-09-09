<?php

namespace App\Livewire;

use App\Models\ReturnRequest;
use App\Services\ReturnService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Returns')]
class Returns extends Component
{
    use WithPagination;

    /** Request form (RD). */
    public string $imeis = '';

    public string $note = '';

    /** @var array{request: ?array, rejected: array<string,string>}|null */
    public ?array $result = null;

    /** Review actions. */
    public ?string $reviewUuid = null;

    public string $reviewNote = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('returns.access'), 403);
    }

    public function canRequest(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('returns.request') && $user->isScoped();
    }

    public function canReview(): bool
    {
        return (bool) auth()->user()?->can('returns.review');
    }

    public function submit(ReturnService $service): void
    {
        abort_unless($this->canRequest(), 403);
        $this->validate(['imeis' => 'required|string']);

        $tokens = preg_split('/[\s,;]+/', trim($this->imeis), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        try {
            $r = $service->request($tokens, trim($this->note) ?: null, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('imeis', $e->getMessage());

            return;
        }

        $this->result = [
            'request' => $r['request'] ? [
                'uuid' => $r['request']->uuid,
                'count' => $r['request']->requested_count,
                'rd_code' => $r['request']->rd_code,
            ] : null,
            'rejected' => $r['rejected'],
        ];
        $this->reset('imeis', 'note');

        if ($r['request']) {
            session()->flash('status', "Return request submitted for {$r['request']->requested_count} device(s) — awaiting approval.");
        }
    }

    public function startReview(string $uuid): void
    {
        abort_unless($this->canReview(), 403);
        $this->reviewUuid = $uuid;
        $this->reviewNote = '';
    }

    public function cancelReview(): void
    {
        $this->reset('reviewUuid', 'reviewNote');
    }

    public function approve(ReturnService $service): void
    {
        abort_unless($this->canReview(), 403);
        $request = ReturnRequest::where('uuid', $this->reviewUuid)->firstOrFail();

        try {
            $service->approve($request, trim($this->reviewNote) ?: null, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('reviewNote', $e->getMessage());

            return;
        }

        $this->cancelReview();
        session()->flash('status', "Approved — {$request->fresh()->approved_count} device(s) returned to {$request->rd_code} stock.");
    }

    public function reject(ReturnService $service): void
    {
        abort_unless($this->canReview(), 403);
        $request = ReturnRequest::where('uuid', $this->reviewUuid)->firstOrFail();

        try {
            $service->reject($request, trim($this->reviewNote) ?: null, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('reviewNote', $e->getMessage());

            return;
        }

        $this->cancelReview();
        session()->flash('status', 'Request rejected.');
    }

    public function render()
    {
        $pending = $this->canReview()
            ? ReturnRequest::with('requester')->where('status', 'pending')->latest('id')->get()
            : collect();

        $mine = $this->canRequest()
            ? ReturnRequest::with('reviewer')->where('requested_by', auth()->id())->latest('id')->limit(20)->get()
            : collect();

        $history = $this->canReview()
            ? ReturnRequest::with(['requester', 'reviewer'])->whereIn('status', ['approved', 'rejected'])->latest('reviewed_at')->limit(20)->get()
            : collect();

        return view('livewire.returns', [
            'pending' => $pending,
            'mine' => $mine,
            'history' => $history,
        ]);
    }
}
