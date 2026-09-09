<?php

namespace App\Livewire;

use App\Models\Pjp as PjpModel;
use App\Models\PjpDay;
use App\Services\PjpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('PJP')]
class Pjp extends Component
{
    #[Url]
    public string $tab = '';

    // ---- planner (TSO) -------------------------------------------------
    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $month = null;

    public ?int $openDayId = null;

    public string $dayStatus = 'planned';

    public string $dayNotes = '';

    /** @var list<string> */
    public array $dayRetailers = [];

    public string $rtSearch = '';

    // ---- review (ASM / NSM) -----------------------------------------
    public ?string $reviewUuid = null;

    public string $reviewNote = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('pjp.access'), 403);

        $tz = config('pjp.timezone');
        $this->year ??= (int) Carbon::now($tz)->year;
        $this->month ??= (int) Carbon::now($tz)->month;

        $this->tab = $this->tab ?: match (true) {
            $this->canPlan() => 'plan',
            $this->canAsm() => 'asm',
            $this->canNsm() => 'nsm',
            default => 'report',
        };
    }

    /** Only field TSOs own a plan — approvers/admins use the review tabs. */
    public function canPlan(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can('pjp.create')
            && ! $user->canAny(['pjp.asm_review', 'pjp.nsm_final_approve']);
    }

    public function canAsm(): bool
    {
        return (bool) auth()->user()?->can('pjp.asm_review');
    }

    public function canNsm(): bool
    {
        return (bool) auth()->user()?->can('pjp.nsm_final_approve');
    }

    public function canReport(): bool
    {
        return (bool) auth()->user()?->can('pjp.report');
    }

    // ---- planner actions --------------------------------------------

    private function myPjp(PjpService $service): PjpModel
    {
        return $service->openMonth(auth()->user(), $this->year, $this->month);
    }

    public function openDay(int $dayId): void
    {
        $day = PjpDay::with('retailers')->findOrFail($dayId);
        abort_unless($day->pjp->tso_id === auth()->id(), 403);

        $this->openDayId = $day->id;
        $this->dayStatus = $day->day_status === 'no_plan' ? 'planned' : $day->day_status;
        $this->dayNotes = (string) $day->notes;
        $this->dayRetailers = $day->retailers->pluck('rt_code')->all();
        $this->rtSearch = '';
    }

    public function closeDay(): void
    {
        $this->reset('openDayId', 'dayNotes', 'dayRetailers', 'rtSearch');
    }

    public function toggleRt(string $code): void
    {
        $this->dayRetailers = in_array($code, $this->dayRetailers, true)
            ? array_values(array_diff($this->dayRetailers, [$code]))
            : [...$this->dayRetailers, $code];
    }

    public function saveDay(PjpService $service): void
    {
        abort_unless($this->canPlan(), 403);
        $day = PjpDay::findOrFail($this->openDayId);
        abort_unless($day->pjp->tso_id === auth()->id(), 403);

        try {
            $service->saveDay($day, $this->dayStatus, $this->dayNotes, $this->dayRetailers);
        } catch (RuntimeException $e) {
            $this->addError('day', $e->getMessage());

            return;
        }

        $this->closeDay();
        session()->flash('status', 'Day saved.');
    }

    public function logVisit(PjpService $service, int $dayId, string $rtCode, array $gps = []): void
    {
        abort_unless($this->canPlan(), 403);
        $day = PjpDay::findOrFail($dayId);

        try {
            $service->logVisit(auth()->user(), $day, $rtCode, $gps);
            session()->flash('status', "Visit logged for {$rtCode}.");
        } catch (RuntimeException $e) {
            $this->addError('day', $e->getMessage());
        }
    }

    public function submit(PjpService $service): void
    {
        abort_unless(auth()->user()?->can('pjp.submit'), 403);
        $pjp = $this->myPjp($service);

        try {
            $service->submit($pjp, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('day', $e->getMessage());

            return;
        }

        session()->flash('status', 'PJP submitted for ASM review.');
    }

    // ---- review actions -------------------------------------------

    public function startReview(string $uuid): void
    {
        $this->reviewUuid = $uuid;
        $this->reviewNote = '';
        $this->resetErrorBag();
    }

    public function cancelReview(): void
    {
        $this->reset('reviewUuid', 'reviewNote');
    }

    public function act(PjpService $service, string $action): void
    {
        $pjp = PjpModel::where('uuid', $this->reviewUuid)->firstOrFail();
        $me = auth()->user();
        $note = trim($this->reviewNote) ?: null;
        $isAsm = str_starts_with($action, 'asm_');
        abort_unless($isAsm ? $this->canAsm() : $this->canNsm(), 403);

        try {
            match ($action) {
                'asm_revision' => $service->asmRequestRevision($pjp, $me, $this->requireNote()),
                'asm_approve' => $service->asmApprove($pjp, $me, $note),
                'nsm_revision' => $service->nsmRequestRevision($pjp, $me, $this->requireNote()),
                'nsm_reject' => $service->nsmReject($pjp, $me, $note),
                'nsm_final' => $service->nsmFinalApprove($pjp, $me, $note),
                default => abort(400),
            };
        } catch (RuntimeException $e) {
            $this->addError('reviewNote', $e->getMessage());

            return;
        }

        $this->cancelReview();
        session()->flash('status', 'Done.');
    }

    private function requireNote(): string
    {
        $this->validate(['reviewNote' => ['required', 'string', 'max:2000']]);

        return trim($this->reviewNote);
    }

    protected function rules(): array
    {
        return ['reviewNote' => ['nullable', 'string', 'max:2000']];
    }

    public function render(PjpService $service)
    {
        $data = [
            'statuses' => config('pjp.statuses'),
            'dayStatuses' => config('pjp.day_statuses'),
            'monthName' => Carbon::create($this->year, $this->month, 1)->format('F Y'),
        ];

        if ($this->tab === 'plan' && $this->canPlan()) {
            $pjp = $this->myPjp($service);
            $days = $pjp->days()->withCount(['retailers', 'visits'])->get();
            $data['pjp'] = $pjp;
            $data['days'] = $days;
            $data['openDay'] = $this->openDayId ? PjpDay::with('retailers', 'visits')->find($this->openDayId) : null;
            $data['retailerOptions'] = $this->scopedRetailers();
        }

        if ($this->tab === 'asm' && $this->canAsm()) {
            $data['asmQueue'] = PjpModel::with(['tso'])
                ->where('asm_id', auth()->id())
                ->whereIn('status', ['submitted', 'resubmitted', 'asm_review'])
                ->latest('submitted_at')->get();
        }

        if ($this->tab === 'nsm' && $this->canNsm()) {
            $data['nsmQueue'] = PjpModel::with(['tso', 'asm'])
                ->where('nsm_id', auth()->id())
                ->whereIn('status', ['forwarded_to_nsm', 'nsm_review'])
                ->latest('forwarded_to_nsm_at')->get();
        }

        if ($this->reviewUuid) {
            $data['reviewPjp'] = PjpModel::with(['tso', 'asm', 'days.retailers', 'days.visits', 'events.actor'])
                ->where('uuid', $this->reviewUuid)->first();
        }

        return view('livewire.pjp', $data);
    }

    /** Retailers this TSO may plan — their RD scope, filtered by search. */
    private function scopedRetailers()
    {
        $codes = auth()->user()->scopedRdCodes();
        $s = trim($this->rtSearch);

        return DB::table('retailers')
            ->when($codes, fn ($q) => $q->whereIn('rd_code', $codes))
            ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', $s.'%')->orWhere('name', 'like', '%'.$s.'%')
                ->orWhere('phone', 'like', $s.'%')))
            ->orderBy('code')->limit(60)
            ->get(['code', 'name', 'rd_code', 'area', 'address', 'phone']);
    }
}
