<?php

namespace App\Livewire;

use App\Services\Reporting\FilterOptions;
use App\Support\MapConfig;
use App\Support\RecordScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Retailer Map')]
class RetailerMap extends Component
{
    use WithPagination;

    #[Url]
    public string $rdCode = '';

    #[Url]
    public string $area = '';

    #[Url]
    public string $tso = '';

    #[Url]
    public string $search = '';

    /** RT code whose TSO visit timeline is open. */
    public ?string $timelineRt = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['rdCode', 'area', 'tso', 'search'], true)) {
            $this->resetPage();
        }
    }

    /** Retailers matching the current filters, RD-scoped to the viewer. */
    private function base(): Builder
    {
        $codes = RecordScope::rdCodes();

        return DB::table('retailers')
            ->when($codes, fn ($q) => $q->whereIn('rd_code', $codes))
            ->when($this->rdCode !== '', fn ($q) => $q->where('rd_code', $this->rdCode))
            ->when($this->area !== '', fn ($q) => $q->where('area', $this->area))
            ->when($this->tso !== '', fn ($q) => $q->whereIn('code', fn ($sub) => $sub
                ->select('rt_code')->distinct()->from('sales_activation_records')
                ->where('tso', $this->tso)->whereNotNull('rt_code')->where('rt_code', '<>', '')))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', $this->search.'%')
                ->orWhere('name', 'like', '%'.$this->search.'%')));
    }

    /**
     * Pin (or move) a retailer's location. Restricted to retailers inside the
     * viewer's RD scope so a scoped user can only map their own retailers.
     */
    public function mapRetailer(string $code, float $lat, float $lng): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
            $this->addError('map', 'Pick a valid point on the map.');

            return;
        }

        $scope = RecordScope::rdCodes();

        $updated = DB::table('retailers')
            ->where('code', $code)
            ->when($scope, fn ($q) => $q->whereIn('rd_code', $scope))
            ->update(['latitude' => round($lat, 7), 'longitude' => round($lng, 7), 'updated_at' => now()]);

        session()->flash('status', $updated ? "Location saved for {$code}." : 'That retailer is outside your access.');
    }

    public function showTimeline(string $rtCode): void
    {
        $this->timelineRt = $rtCode;
    }

    public function closeTimeline(): void
    {
        $this->timelineRt = null;
    }

    /** PJP visit check-ins recorded at the open retailer, newest first. */
    private function timeline(): array
    {
        if ($this->timelineRt === null) {
            return [];
        }

        return DB::table('pjp_visits')
            ->leftJoin('users', 'users.id', '=', 'pjp_visits.user_id')
            ->where('pjp_visits.rt_code', $this->timelineRt)
            ->orderByDesc('pjp_visits.visited_at')
            ->limit(200)
            ->get([
                'pjp_visits.visited_at', 'pjp_visits.latitude', 'pjp_visits.longitude',
                'pjp_visits.note', 'users.name as tso_name',
            ])
            ->all();
    }

    public function render(FilterOptions $options)
    {
        $mapped = (clone $this->base())
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('code')->limit(2000)
            ->get(['code', 'name', 'rd_code', 'area', 'phone', 'latitude', 'longitude']);

        $list = (clone $this->base())
            ->orderBy('code')
            ->paginate(25, ['code', 'name', 'rd_code', 'area', 'phone', 'latitude', 'longitude']);

        $codes = RecordScope::rdCodes();
        $areas = DB::table('retailers')
            ->when($codes, fn ($q) => $q->whereIn('rd_code', $codes))
            ->whereNotNull('area')->where('area', '<>', '')
            ->distinct()->orderBy('area')->pluck('area');

        return view('livewire.retailer-map', [
            'points' => $mapped,
            'list' => $list,
            'total' => (clone $this->base())->count(),
            'unmappedCount' => (clone $this->base())
                ->where(fn ($w) => $w->whereNull('latitude')->orWhereNull('longitude'))->count(),
            'rdOptions' => $options->distributors(),
            'areaOptions' => $areas,
            'tsoOptions' => $options->tso(),
            'timeline' => $this->timeline(),
            'timelineRetailer' => $this->timelineRt
                ? DB::table('retailers')->where('code', $this->timelineRt)->first()
                : null,
            'mapsEnabled' => MapConfig::enabled(),
            'apiKey' => MapConfig::apiKey(),
            'centre' => MapConfig::defaultCentre(),
        ]);
    }
}
