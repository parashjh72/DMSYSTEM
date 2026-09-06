<?php

namespace App\Livewire;

use App\Services\Export\ExportService;
use App\Services\Reporting\FilterOptions;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\StockReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Stock Report')]
class StockReport extends Component
{
    use WithPagination;

    #[Url]
    public string $type = 'rd';

    #[Url]
    public ?string $rdCode = null;

    #[Url]
    public ?string $rtCode = null;

    public const TYPES = [
        'rd' => 'RD-wise stock',
        'rt' => 'RT-wise stock',
        'model' => 'Model-wise stock',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedRdCode(): void
    {
        $this->rtCode = null;
        $this->resetPage();
    }

    public function updatedRtCode(): void
    {
        $this->resetPage();
    }

    public function export(ExportService $exports)
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);

        $exportType = ['rd' => 'stock_rd', 'rt' => 'stock_rt', 'model' => 'stock_model'][$this->type];
        $filters = ReportFilters::fromArray([
            'rd_code' => $this->rdCode,
            'rt_code' => $this->type === 'rt' ? $this->rtCode : null,
        ]);

        $exports->queue($exportType, $filters, auth()->id());
        session()->flash('status', 'Stock export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function render(StockReportService $stock, FilterOptions $options)
    {
        $rd = $this->rdCode ?: null;
        $rt = $this->rtCode ?: null;

        $columns = ['models' => [], 'hasOther' => false, 'totals' => [], 'otherTotal' => 0, 'grandTotal' => 0];

        if ($this->type === 'model') {
            $rows = $stock->modelWise($rd, 50);
        } else {
            $columns = $stock->modelColumns($this->type, $rd, $this->type === 'rt' ? $rt : null);
            $rows = $this->type === 'rt'
                ? $stock->rtWise($rd, $rt, $columns['models'], 50)
                : $stock->rdWise($rd, $columns['models'], 50);
        }

        return view('livewire.stock-report', [
            'rows' => $rows,
            'columns' => $columns,
            'summary' => $stock->summary($rd, $this->type === 'rt' ? $rt : null),
            'rdOptions' => $options->distributors(),
            'rtOptions' => $options->retailers($rd)['options'],
        ]);
    }
}
