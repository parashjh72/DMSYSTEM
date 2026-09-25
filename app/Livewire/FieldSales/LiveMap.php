<?php

namespace App\Livewire\FieldSales;

use App\Livewire\FieldSales\Concerns\WithHierarchyFilters;
use App\Services\FieldSales\LiveStaff;
use App\Support\NepaliDate;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Live Staff Map')]
class LiveMap extends Component
{
    use WithHierarchyFilters;

    /** @var list<array<string, mixed>> */
    public array $staff = [];

    public string $refreshedAt = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('fs.map.view'), 403);
        $this->refresh();
    }

    public function updated(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        abort_unless(auth()->user()?->can('fs.map.view'), 403);

        $this->staff = app(LiveStaff::class)->snapshot(auth()->user(), $this->regionId, $this->areaId, $this->rdCode ?: null);
        $this->refreshedAt = Carbon::now(config('field_sales.timezone'))->format('H:i:s');
    }

    public function render()
    {
        $today = Carbon::now(config('field_sales.timezone'));

        return view('livewire.field-sales.live-map', [
            'options' => $this->hierarchyOptions(),
            'counts' => collect($this->staff)->countBy('state')->all(),
            'states' => LiveStaff::STATES,
            'todayLabel' => $today->format('D, d M Y').' · '.NepaliDate::formatLong($today).' BS',
        ]);
    }
}
