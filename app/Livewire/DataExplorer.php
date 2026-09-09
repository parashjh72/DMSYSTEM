<?php

namespace App\Livewire;

use App\Services\Export\ExportService;
use App\Services\Reporting\ReportFilters;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Data Explorer')]
class DataExplorer extends Component
{
    use WithPagination;

    #[Url]
    public array $f = [];

    #[Url]
    public int $perPage = 50;

    #[Url]
    public string $sort = 'id';

    #[Url]
    public string $dir = 'desc';

    private const SORTABLE = ['id', 'imei', 'st_date', 'activation_date', 'activation_days', 'model', 'product_code', 'rd_code'];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('explorer.view'), 403);
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->f = [];
        $this->resetPage();
    }

    public function sortBy(string $col): void
    {
        if (! in_array($col, self::SORTABLE, true)) {
            return;
        }
        [$this->sort, $this->dir] = $this->sort === $col
            ? [$col, $this->dir === 'asc' ? 'desc' : 'asc']
            : [$col, 'asc'];
    }

    public function export()
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);
        app(ExportService::class)->queue('records', ReportFilters::fromArray($this->f), auth()->id());
        session()->flash('status', 'Export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function render()
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'id';
        $dir = $this->dir === 'asc' ? 'asc' : 'desc';

        $query = DB::table('sales_activation_records')
            ->select(['id', 'imei', 'model', 'product_code', 'tso', 'rd_code', 'rt_code', 'st_date', 'activation_date', 'sell_in_date', 'activation_days', 'source', 'last_import_batch_id']);

        ReportFilters::fromArray($this->f)->apply($query);

        $records = $query->orderBy($sort, $dir)->paginate(min($this->perPage, 250));

        return view('livewire.data-explorer', ['records' => $records]);
    }
}
