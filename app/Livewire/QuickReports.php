<?php

namespace App\Livewire;

use App\Services\Export\ExportService;
use App\Services\Reporting\FilterOptions;
use App\Services\Reporting\PriceService;
use App\Services\Reporting\QuickReportService;
use App\Services\Reporting\ReportFilters;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Quick Reports')]
class QuickReports extends Component
{
    use WithPagination;

    #[Url]
    public string $type = 'act_vs_st';

    #[Url]
    public ?string $rdCode = null;

    #[Url]
    public ?string $dateFrom = null;

    #[Url]
    public ?string $dateTo = null;

    /** Value report: which date the price is applied against. */
    #[Url]
    public string $valueBasis = 'activation_date';

    public const TYPES = [
        'act_vs_st' => 'Activation vs Sell-thru',
        'act_value' => 'Activation value (by price)',
        'zero_stock' => 'Zero-stock RT — sold-out, no sell-thru',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    private function filters(): ReportFilters
    {
        return ReportFilters::fromArray(array_filter([
            'rd_code' => $this->rdCode,
            'st_date_from' => $this->type === 'act_vs_st' ? $this->dateFrom : null,
            'st_date_to' => $this->type === 'act_vs_st' ? $this->dateTo : null,
        ]));
    }

    public function export(ExportService $exports)
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);

        $type = match ($this->type) {
            'zero_stock' => 'quick_zero_stock',
            'act_value' => 'quick_act_value',
            default => abort(422, 'Use the on-screen table for this report.'),
        };

        $filters = ReportFilters::fromArray(array_filter([
            'rd_code' => $this->rdCode,
            'value_from' => $this->dateFrom,
            'value_to' => $this->dateTo,
            'value_basis' => $this->valueBasis,
        ]));

        $exports->queue($type, $filters, auth()->id());
        session()->flash('status', 'Export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function render(QuickReportService $quick, PriceService $prices, FilterOptions $options)
    {
        $f = $this->filters();

        $value = collect();
        if ($this->type === 'act_value') {
            $value = $prices->valueByModel($this->dateFrom, $this->dateTo, $this->valueBasis, $this->rdCode ?: null);
        }

        return view('livewire.quick-reports', [
            'series' => $this->type === 'act_vs_st' ? $quick->activationVsSellThrough($f) : collect(),
            'value' => $value,
            'rows' => $this->type === 'zero_stock' ? $quick->zeroStockSoldNotSellThrough($f, 50) : null,
            'rdOptions' => $options->distributors(),
            'symbol' => config('pricing.symbol'),
        ]);
    }
}
