<?php

namespace App\Livewire;

use App\Models\PromoterRequest;
use App\Services\PromoterRequestService;
use App\Support\RecordScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('RA Requests')]
class PromoterRequests extends Component
{
    // ---- request form (TSO) ------------------------------------------
    public string $type = 'real_ra';

    public string $rtCode = '';

    public string $rtSearch = '';

    public string $promoterName = '';

    public int $proposedTarget = 0;

    public string $note = '';

    // ---- review ----------------------------------------------------
    public ?string $reviewUuid = null;

    public string $reviewNote = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ra-requests.access'), 403);
    }

    public function canRequest(): bool
    {
        return (bool) auth()->user()?->can('promoter_requests.create');
    }

    public function canAsm(): bool
    {
        return (bool) auth()->user()?->can('promoter_requests.approve_asm');
    }

    public function canNsm(): bool
    {
        return (bool) auth()->user()?->can('promoter_requests.approve_nsm');
    }

    public function pickRetailer(string $code): void
    {
        $rt = $this->scopedRetailers()->firstWhere('code', $code);
        if ($rt) {
            $this->rtCode = $rt->code;
            $this->rtSearch = trim($rt->code.' — '.$rt->name, ' —');
        }
    }

    public function submit(PromoterRequestService $service): void
    {
        abort_unless($this->canRequest(), 403);

        $data = $this->validate([
            'type' => ['required', Rule::in(array_keys(config('promoters.types')))],
            'rtCode' => ['required', 'string'],
            'promoterName' => ['nullable', 'string', 'max:120'],
            'proposedTarget' => ['required', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->create(auth()->user(), [
                'type' => $data['type'],
                'rt_code' => $data['rtCode'],
                'promoter_name' => $data['promoterName'],
                'proposed_target' => $data['proposedTarget'],
                'note' => $data['note'],
            ]);
        } catch (RuntimeException $e) {
            $this->addError('rtCode', $e->getMessage());

            return;
        }

        $this->reset('rtCode', 'rtSearch', 'promoterName', 'proposedTarget', 'note');
        session()->flash('status', 'RA request submitted — awaiting ASM approval.');
    }

    public function startReview(string $uuid): void
    {
        $this->reviewUuid = $uuid;
        $this->reviewNote = '';
    }

    public function cancelReview(): void
    {
        $this->reset('reviewUuid', 'reviewNote');
    }

    public function approve(PromoterRequestService $service): void
    {
        $request = PromoterRequest::where('uuid', $this->reviewUuid)->firstOrFail();

        try {
            if ($request->status === 'pending_asm') {
                abort_unless($this->canAsm() && $this->inAsmScope($request), 403);
                $service->approveAsm($request, auth()->user(), trim($this->reviewNote) ?: null);
                $msg = 'Approved — forwarded to NSM.';
            } else {
                abort_unless($this->canNsm(), 403);
                $service->approveNsm($request, auth()->user(), trim($this->reviewNote) ?: null);
                $msg = 'Approved — promoter created.';
            }
        } catch (RuntimeException $e) {
            $this->addError('reviewNote', $e->getMessage());

            return;
        }

        $this->cancelReview();
        session()->flash('status', $msg);
    }

    public function rejectRequest(PromoterRequestService $service): void
    {
        $request = PromoterRequest::where('uuid', $this->reviewUuid)->firstOrFail();
        $stage = $request->status === 'pending_asm' ? 'asm' : 'nsm';

        if ($stage === 'asm') {
            abort_unless($this->canAsm() && $this->inAsmScope($request), 403);
        } else {
            abort_unless($this->canNsm(), 403);
        }

        try {
            $service->reject($request, auth()->user(), $stage, trim($this->reviewNote) ?: null);
        } catch (RuntimeException $e) {
            $this->addError('reviewNote', $e->getMessage());

            return;
        }

        $this->cancelReview();
        session()->flash('status', 'Request rejected.');
    }

    private function inAsmScope(PromoterRequest $request): bool
    {
        $scope = auth()->user()->scopedRdCodes();

        return $scope === [] || in_array($request->rd_code, $scope, true);
    }

    /** Retailers the current user may pick from (their RD scope), filtered by search. */
    private function scopedRetailers()
    {
        $scope = RecordScope::rdCodes();
        $search = trim($this->rtSearch);

        return DB::table('retailers')
            ->when($scope, fn ($q, $c) => $q->whereIn('rd_code', $c))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', $search.'%')
                ->orWhere('name', 'like', '%'.$search.'%')))
            ->orderBy('code')
            ->limit(50)
            ->get(['code', 'name', 'rd_code']);
    }

    public function render(PromoterRequestService $service)
    {
        $preview = $this->rtCode ? $service->salesPreview($this->rtCode) : [];

        $asmQueue = $this->canAsm()
            ? PromoterRequest::with('requester')->where('status', 'pending_asm')
                ->when(auth()->user()->scopedRdCodes() !== [],
                    fn ($q) => $q->whereIn('rd_code', auth()->user()->scopedRdCodes()))
                ->latest('id')->get()
            : collect();

        $nsmQueue = $this->canNsm()
            ? PromoterRequest::with(['requester', 'asmReviewer'])->where('status', 'pending_nsm')->latest('id')->get()
            : collect();

        $mine = $this->canRequest()
            ? PromoterRequest::where('requested_by', auth()->id())->latest('id')->limit(25)->get()
            : collect();

        return view('livewire.promoter-requests', [
            'types' => config('promoters.types'),
            'retailerOptions' => $this->canRequest() ? $this->scopedRetailers() : collect(),
            'preview' => $preview,
            'asmQueue' => $asmQueue,
            'nsmQueue' => $nsmQueue,
            'mine' => $mine,
        ]);
    }
}
