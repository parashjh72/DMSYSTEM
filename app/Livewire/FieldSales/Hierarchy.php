<?php

namespace App\Livewire\FieldSales;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\Region;
use App\Models\RetailDistributor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Field Sales Setup → Regions & Areas: company → region → area → distributor.
 */
#[Layout('components.layouts.app')]
#[Title('Regions & Areas')]
class Hierarchy extends Component
{
    public ?int $regionEditId = null;

    public string $regionCode = '';

    public string $regionName = '';

    public bool $regionActive = true;

    public ?int $areaEditId = null;

    public ?int $areaRegionId = null;

    public string $areaCode = '';

    public string $areaName = '';

    public bool $areaActive = true;

    /** Area whose distributors are being managed. */
    public ?int $selectedAreaId = null;

    public string $addRdCode = '';

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorizeSetup();
    }

    public function editRegion(int $id): void
    {
        $region = Region::findOrFail($id);
        $this->regionEditId = $region->id;
        $this->regionCode = $region->code;
        $this->regionName = $region->name;
        $this->regionActive = $region->active;
    }

    public function saveRegion(): void
    {
        $this->authorizeSetup();
        $data = $this->validate([
            'regionCode' => ['required', 'string', 'max:40', Rule::unique('fs_regions', 'code')->ignore($this->regionEditId)],
            'regionName' => ['required', 'string', 'max:120'],
            'regionActive' => ['boolean'],
        ], [], ['regionCode' => 'code', 'regionName' => 'name']);

        $values = [
            'code' => strtoupper(trim($data['regionCode'])),
            'name' => trim($data['regionName']),
            'active' => $data['regionActive'],
        ];
        $this->regionEditId ? Region::findOrFail($this->regionEditId)->update($values) : Region::query()->create($values);

        $this->reset('regionEditId', 'regionCode', 'regionName', 'regionActive');
        $this->done('Region saved.');
    }

    public function deleteRegion(int $id): void
    {
        $this->authorizeSetup();
        $region = Region::withCount('areas')->findOrFail($id);
        if ($region->areas_count > 0) {
            $this->error = 'Move or delete this region’s areas first.';

            return;
        }
        $region->delete();
        $this->done('Region deleted.');
    }

    public function editArea(int $id): void
    {
        $area = Area::findOrFail($id);
        $this->areaEditId = $area->id;
        $this->areaRegionId = $area->region_id;
        $this->areaCode = $area->code;
        $this->areaName = $area->name;
        $this->areaActive = $area->active;
    }

    public function saveArea(): void
    {
        $this->authorizeSetup();
        $data = $this->validate([
            'areaRegionId' => ['required', 'integer', 'exists:fs_regions,id'],
            'areaCode' => ['required', 'string', 'max:40', Rule::unique('fs_areas', 'code')->ignore($this->areaEditId)],
            'areaName' => ['required', 'string', 'max:120'],
            'areaActive' => ['boolean'],
        ], [], ['areaRegionId' => 'region', 'areaCode' => 'code', 'areaName' => 'name']);

        $values = [
            'region_id' => $data['areaRegionId'],
            'code' => strtoupper(trim($data['areaCode'])),
            'name' => trim($data['areaName']),
            'active' => $data['areaActive'],
        ];
        $this->areaEditId ? Area::findOrFail($this->areaEditId)->update($values) : Area::query()->create($values);

        $this->reset('areaEditId', 'areaRegionId', 'areaCode', 'areaName', 'areaActive');
        $this->done('Area saved.');
    }

    public function deleteArea(int $id): void
    {
        $this->authorizeSetup();
        Area::findOrFail($id)->delete();
        if ($this->selectedAreaId === $id) {
            $this->selectedAreaId = null;
        }
        $this->done('Area deleted; its distributors are now unassigned.');
    }

    public function selectArea(int $id): void
    {
        $this->selectedAreaId = $id;
        $this->addRdCode = '';
    }

    public function assignDistributor(): void
    {
        $this->authorizeSetup();
        $area = Area::findOrFail($this->selectedAreaId);
        $distributor = RetailDistributor::query()->where('code', $this->addRdCode)->first();
        if (! $distributor) {
            $this->error = 'Choose a distributor to add.';

            return;
        }

        DB::table('fs_area_distributors')->upsert([[
            'area_id' => $area->id,
            'retail_distributor_id' => $distributor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['retail_distributor_id'], ['area_id', 'updated_at']);

        $this->addRdCode = '';
        $this->done("{$distributor->code} added to {$area->name}.");
    }

    public function unassignDistributor(int $distributorId): void
    {
        $this->authorizeSetup();
        DB::table('fs_area_distributors')->where('retail_distributor_id', $distributorId)->delete();
        $this->done('Distributor removed from the area.');
    }

    private function done(string $message): void
    {
        $this->error = null;
        $this->flash = $message;
    }

    private function authorizeSetup(): void
    {
        abort_unless(auth()->user()?->can('fs.setup.manage'), 403);
    }

    public function render()
    {
        $assignedIds = DB::table('fs_area_distributors')->pluck('area_id', 'retail_distributor_id');
        $selected = $this->selectedAreaId ? Area::with(['distributors' => fn ($q) => $q->orderBy('code')])->find($this->selectedAreaId) : null;

        return view('livewire.field-sales.hierarchy', [
            'regions' => Region::query()->withCount('areas')->orderBy('name')->get(),
            'areas' => Area::query()->with('region')->withCount('distributors')->orderBy('name')->get(),
            'selected' => $selected,
            'unassigned' => RetailDistributor::query()
                ->whereNotIn('id', $assignedIds->keys())
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'unassignedCount' => RetailDistributor::query()->count() - $assignedIds->count(),
        ]);
    }
}
