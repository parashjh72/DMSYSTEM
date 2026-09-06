<?php

namespace App\Livewire;

use App\Models\Scheme;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Schemes')]
class Schemes extends Component
{
    public bool $showForm = false;

    public ?string $editingUuid = null;

    public string $name = '';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public string $selloutBasis = 'activation_date';

    public string $qualifiedModels = 'running';

    public string $status = 'draft';

    public string $note = '';

    /** @var array<int, array{slab_no:int,label:string,min_value:string,max_value:string,payout_percent:string,reward:string}> */
    public array $slabs = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
    }

    public function newScheme(): void
    {
        $this->reset('editingUuid', 'name', 'note');
        $this->effectiveFrom = now()->toDateString();
        $this->effectiveTo = now()->addMonths(2)->toDateString();
        $this->selloutBasis = 'activation_date';
        $this->qualifiedModels = 'running';
        $this->status = 'draft';
        $this->slabs = [$this->blankSlab(1)];
        $this->showForm = true;
    }

    public function edit(string $uuid): void
    {
        $scheme = Scheme::with('slabs')->where('uuid', $uuid)->firstOrFail();
        $this->editingUuid = $scheme->uuid;
        $this->name = $scheme->name;
        $this->effectiveFrom = $scheme->effective_from->toDateString();
        $this->effectiveTo = $scheme->effective_to->toDateString();
        $this->selloutBasis = $scheme->sellout_basis;
        $this->qualifiedModels = $scheme->qualified_models;
        $this->status = $scheme->status;
        $this->note = (string) $scheme->note;
        $this->slabs = $scheme->slabs->map(fn ($s) => [
            'slab_no' => $s->slab_no,
            'label' => (string) $s->label,
            'min_value' => (string) (int) $s->min_value,
            'max_value' => $s->max_value === null ? '' : (string) (int) $s->max_value,
            'payout_percent' => rtrim(rtrim((string) $s->payout_percent, '0'), '.'),
            'reward' => (string) $s->reward,
        ])->values()->all() ?: [$this->blankSlab(1)];
        $this->showForm = true;
    }

    public function addSlab(): void
    {
        $this->slabs[] = $this->blankSlab(count($this->slabs) + 1);
    }

    public function removeSlab(int $i): void
    {
        unset($this->slabs[$i]);
        $this->slabs = array_values($this->slabs);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);

        $data = $this->validate([
            'name' => 'required|string|max:191',
            'effectiveFrom' => 'required|date',
            'effectiveTo' => 'required|date|after_or_equal:effectiveFrom',
            'selloutBasis' => 'in:activation_date,st_date',
            'qualifiedModels' => 'in:running,out,all',
            'status' => 'in:draft,active,closed',
            'slabs.*.min_value' => 'required|numeric|min:0',
            'slabs.*.payout_percent' => 'required|numeric|min:0',
        ]);

        $scheme = Scheme::updateOrCreate(
            ['uuid' => $this->editingUuid ?? (string) Str::uuid()],
            [
                'name' => $data['name'],
                'effective_from' => $data['effectiveFrom'],
                'effective_to' => $data['effectiveTo'],
                'sellout_basis' => $data['selloutBasis'],
                'qualified_models' => $data['qualifiedModels'],
                'status' => $data['status'],
                'note' => $this->note ?: null,
                'created_by' => auth()->id(),
            ],
        );

        $scheme->slabs()->delete();
        foreach (array_values($this->slabs) as $i => $s) {
            $scheme->slabs()->create([
                'slab_no' => (int) ($s['slab_no'] ?: $i + 1),
                'label' => $s['label'] ?: null,
                'min_value' => (float) $s['min_value'],
                'max_value' => $s['max_value'] === '' ? null : (float) $s['max_value'],
                'payout_percent' => (float) $s['payout_percent'],
                'reward' => $s['reward'] ?: null,
            ]);
        }

        $this->reset('showForm', 'editingUuid');
        session()->flash('status', "Scheme '{$scheme->name}' saved.");
    }

    public function delete(string $uuid): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        Scheme::where('uuid', $uuid)->delete();
    }

    private function blankSlab(int $no): array
    {
        return ['slab_no' => $no, 'label' => '', 'min_value' => '', 'max_value' => '', 'payout_percent' => '', 'reward' => ''];
    }

    public function render()
    {
        return view('livewire.schemes', [
            'schemes' => Scheme::withCount('slabs')->latest()->get(),
        ]);
    }
}
