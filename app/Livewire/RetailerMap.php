<?php

namespace App\Livewire;

use App\Support\MapConfig;
use App\Support\RecordScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Retailer Map')]
class RetailerMap extends Component
{
    #[Url]
    public string $rdCode = '';

    #[Url]
    public string $area = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    private function base(): Builder
    {
        $codes = RecordScope::rdCodes();

        return DB::table('retailers')
            ->when($codes, fn ($q) => $q->whereIn('rd_code', $codes))
            ->when($this->rdCode !== '', fn ($q) => $q->where('rd_code', $this->rdCode))
            ->when($this->area !== '', fn ($q) => $q->where('area', $this->area))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', $this->search.'%')
                ->orWhere('name', 'like', '%'.$this->search.'%')));
    }

    public function render()
    {
        $mapped = (clone $this->base())
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('code')->limit(2000)
            ->get(['code', 'name', 'rd_code', 'area', 'phone', 'latitude', 'longitude']);

        $codes = RecordScope::rdCodes();
        $scoped = DB::table('retailers')->when($codes, fn ($q) => $q->whereIn('rd_code', $codes));

        return view('livewire.retailer-map', [
            'points' => $mapped,
            'unmappedCount' => (clone $this->base())
                ->where(fn ($w) => $w->whereNull('latitude')->orWhereNull('longitude'))->count(),
            'rdOptions' => (clone $scoped)->whereNotNull('rd_code')->distinct()->orderBy('rd_code')->pluck('rd_code'),
            'areaOptions' => (clone $scoped)->whereNotNull('area')->where('area', '!=', '')->distinct()->orderBy('area')->pluck('area'),
            'mapsEnabled' => MapConfig::enabled(),
            'apiKey' => MapConfig::apiKey(),
            'centre' => MapConfig::defaultCentre(),
        ]);
    }
}
