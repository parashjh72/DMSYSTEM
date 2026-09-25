<?php

namespace App\Livewire\FieldSales;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\Geofence;
use App\Models\RetailDistributor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Field Sales Setup → Check-in Points: where field officers may punch
 * attendance. Distributors have no coordinates in the DMS, so an Admin places
 * each distributor's point (and any office) on the map here.
 */
#[Layout('components.layouts.app')]
#[Title('Check-in Points')]
class Geofences extends Component
{
    use WithPagination;

    public ?int $editId = null;

    public string $name = '';

    /** distributor | office */
    public string $kind = 'distributor';

    public string $rdCode = '';

    public ?int $areaId = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public int $radius = 200;

    public bool $active = true;

    public string $search = '';

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizeSetup();
        $this->radius = (int) config('field_sales.policy_defaults.geofence_radius_metres');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRdCode(string $code): void
    {
        if ($this->name === '' && $code !== '') {
            $this->name = (string) RetailDistributor::query()->where('code', $code)->value('name') ?: $code;
        }
    }

    /** Called by the map picker. */
    public function setFormLatLng(string $id, float $lat, float $lng): void
    {
        $this->latitude = round($lat, 7);
        $this->longitude = round($lng, 7);
    }

    public function edit(int $id): void
    {
        $point = Geofence::with('distributor')->findOrFail($id);
        $this->editId = $point->id;
        $this->name = $point->name;
        $this->kind = $point->retail_distributor_id ? 'distributor' : 'office';
        $this->rdCode = (string) $point->distributor?->code;
        $this->areaId = $point->area_id;
        $this->latitude = $point->latitude;
        $this->longitude = $point->longitude;
        $this->radius = $point->radius_metres;
        $this->active = $point->active;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeSetup();
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:distributor,office'],
            'rdCode' => ['required_if:kind,distributor', 'nullable', 'exists:retail_distributors,code'],
            'areaId' => ['nullable', 'integer', 'exists:fs_areas,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'integer', 'between:25,5000'],
            'active' => ['boolean'],
        ], [
            'latitude.required' => 'Pin the point on the map.',
            'rdCode.required_if' => 'Choose the distributor.',
        ], ['rdCode' => 'distributor', 'areaId' => 'area']);

        $values = [
            'name' => trim($this->name),
            'retail_distributor_id' => $this->kind === 'distributor'
                ? RetailDistributor::query()->where('code', $this->rdCode)->value('id')
                : null,
            'area_id' => $this->kind === 'office' ? $this->areaId : null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_metres' => $this->radius,
            'active' => $this->active,
        ];

        $this->editId ? Geofence::findOrFail($this->editId)->update($values) : Geofence::create($values);

        $this->resetForm();
        $this->flash = 'Check-in point saved.';
    }

    public function delete(int $id): void
    {
        $this->authorizeSetup();
        Geofence::findOrFail($id)->delete();
        $this->flash = 'Check-in point deleted.';
    }

    private function resetForm(): void
    {
        $this->reset('editId', 'name', 'kind', 'rdCode', 'areaId', 'latitude', 'longitude', 'active');
        $this->radius = (int) config('field_sales.policy_defaults.geofence_radius_metres');
        $this->resetValidation();
    }

    private function authorizeSetup(): void
    {
        abort_unless(auth()->user()?->can('fs.setup.manage'), 403);
    }

    public function render()
    {
        $withPoint = Geofence::query()->whereNotNull('retail_distributor_id')->pluck('retail_distributor_id');

        return view('livewire.field-sales.geofences', [
            'points' => Geofence::query()
                ->with('distributor', 'area')
                ->when($this->search !== '', fn ($q) => $q->where(fn ($s) => $s
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhereHas('distributor', fn ($d) => $d->where('code', 'like', "%{$this->search}%"))))
                ->orderBy('name')
                ->paginate(25),
            'distributors' => RetailDistributor::query()->orderBy('code')->get(['id', 'code', 'name']),
            'areas' => Area::query()->orderBy('name')->pluck('name', 'id'),
            'missing' => RetailDistributor::query()->whereNotIn('id', $withPoint)->orderBy('code')->get(['code', 'name']),
        ]);
    }
}
