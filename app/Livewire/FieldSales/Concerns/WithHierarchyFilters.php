<?php

namespace App\Livewire\FieldSales\Concerns;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\Region;
use App\Services\Reporting\FilterOptions;

/**
 * Region → Area → Distributor filter used across the Field Sales screens.
 */
trait WithHierarchyFilters
{
    public ?int $regionId = null;

    public ?int $areaId = null;

    public string $rdCode = '';

    public function updatedRegionId(): void
    {
        $this->areaId = null;
    }

    /**
     * @return array{regions: array<int, string>, areas: array<int, string>, distributors: array<string, string>}
     */
    protected function hierarchyOptions(): array
    {
        return [
            'regions' => Region::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'areas' => Area::query()->where('active', true)
                ->when($this->regionId, fn ($q, $v) => $q->where('region_id', $v))
                ->orderBy('name')->pluck('name', 'id')->all(),
            'distributors' => app(FilterOptions::class)->distributors(),
        ];
    }
}
