<?php

namespace App\Livewire;

use App\Models\AnnualContract;
use App\Services\Reporting\FilterOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Annual Contracts')]
class AnnualContracts extends Component
{
    use WithPagination;

    #[Url]
    public string $statusFilter = 'active';

    #[Url]
    public string $rdFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fRtCode = '';

    public string $fRtSearch = '';

    public int $fTargetVolume = 0;

    public string $fIncentivePct = '';

    public string $fStartsOn = '';

    public string $fEndsOn = '';

    public string $fStatus = 'active';

    public string $fNotes = '';

    private const FORM = [
        'showForm', 'editingId', 'fRtCode', 'fRtSearch', 'fTargetVolume',
        'fIncentivePct', 'fStartsOn', 'fEndsOn', 'fStatus', 'fNotes',
    ];

    public const STATUSES = ['active' => 'Active', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('contracts.manage'), 403);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRdFilter(): void
    {
        $this->resetPage();
    }

    public function newRow(): void
    {
        $this->reset(self::FORM);
        $tz = config('reports.timezone', 'Asia/Kathmandu');
        $this->fStartsOn = Carbon::now($tz)->toDateString();
        $this->fEndsOn = Carbon::now($tz)->addYear()->subDay()->toDateString();
        $this->fStatus = 'active';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function editRow(int $id): void
    {
        $c = AnnualContract::findOrFail($id);
        $this->editingId = $c->id;
        $this->fRtCode = $c->rt_code;
        $this->fRtSearch = trim($c->rt_code.' — '.$c->rt_name, ' —');
        $this->fTargetVolume = $c->target_volume;
        $this->fIncentivePct = (string) $c->incentive_pct;
        $this->fStartsOn = $c->starts_on->toDateString();
        $this->fEndsOn = $c->ends_on->toDateString();
        $this->fStatus = $c->status;
        $this->fNotes = (string) $c->notes;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->reset(self::FORM);
    }

    public function pickRetailer(string $code): void
    {
        $rt = DB::table('retailers')->where('code', $code)->first();
        if ($rt) {
            $this->fRtCode = $rt->code;
            $this->fRtSearch = trim($rt->code.' — '.$rt->name, ' —');
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('contracts.manage'), 403);

        $data = $this->validate([
            'fRtCode' => ['required', 'string', 'max:40'],
            'fTargetVolume' => ['required', 'integer', 'min:1', 'max:100000000'],
            'fIncentivePct' => ['required', 'numeric', 'min:0', 'max:100'],
            'fStartsOn' => ['required', 'date'],
            'fEndsOn' => ['required', 'date', 'after:fStartsOn'],
            'fStatus' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
            'fNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $rt = DB::table('retailers')->where('code', $data['fRtCode'])->first();
        if (! $rt) {
            $this->addError('fRtCode', 'Pick a retailer from the list.');

            return;
        }

        AnnualContract::updateOrCreate(
            ['id' => $this->editingId],
            [
                'rt_code' => $rt->code,
                'rt_name' => $rt->name,
                'rd_code' => $rt->rd_code,
                'target_volume' => $data['fTargetVolume'],
                'incentive_pct' => $data['fIncentivePct'],
                'starts_on' => $data['fStartsOn'],
                'ends_on' => $data['fEndsOn'],
                'status' => $data['fStatus'],
                'notes' => trim($data['fNotes']) ?: null,
                ...($this->editingId ? [] : ['created_by' => auth()->id()]),
            ],
        );

        $this->cancelForm();
        session()->flash('status', 'Contract saved.');
    }

    public function close(int $id): void
    {
        abort_unless(auth()->user()?->can('contracts.manage'), 403);
        AnnualContract::whereKey($id)->update(['status' => 'closed']);
        session()->flash('status', 'Contract closed.');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()?->can('contracts.manage'), 403);
        AnnualContract::whereKey($id)->delete();
        session()->flash('status', 'Contract deleted.');
    }

    public function render(FilterOptions $options)
    {
        $achievedSub = '(SELECT COUNT(*) FROM sales_activation_records s
            WHERE s.rt_code = annual_contracts.rt_code
              AND s.is_activated = 1
              AND s.activation_date BETWEEN annual_contracts.starts_on AND annual_contracts.ends_on) AS achieved';

        $rows = AnnualContract::query()
            ->with('creator')
            ->selectRaw('annual_contracts.*, '.$achievedSub)
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->rdFilter !== '', fn ($q) => $q->where('rd_code', $this->rdFilter))
            ->orderByDesc('starts_on')
            ->paginate(20);

        return view('livewire.annual-contracts', [
            'rows' => $rows,
            'statuses' => self::STATUSES,
            'rdOptions' => $options->distributors(),
            'rtOptions' => $this->showForm ? $options->retailers(null, $this->fRtSearch)['options'] : [],
        ]);
    }
}
