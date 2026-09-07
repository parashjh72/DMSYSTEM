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

    /** Selected retailer codes (RT-wise tab, multi-select). */
    #[Url]
    public array $rtCodes = [];

    /** '' = both, 'running', 'out' — filters by device_models.status. */
    #[Url]
    public string $lifecycle = '';

    /** Type-ahead filter for the retailer checklist. */
    public string $rtSearch = '';

    /** Paste box for bulk retailer selection. */
    public string $rtPaste = '';

    /** Tokens from the last paste that matched nothing. */
    public array $rtUnmatched = [];

    public const TYPES = [
        'rd' => 'RD-wise stock',
        'rt' => 'RT-wise stock',
        'model' => 'Model-wise stock',
    ];

    /** stock | sellout — the subclass overrides this. */
    protected function mode(): string
    {
        return 'stock';
    }

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
        $this->rtCodes = [];
        $this->rtUnmatched = [];
        $this->resetPage();
    }

    public function updatedLifecycle(): void
    {
        $this->resetPage();
    }

    /** Tick / untick one retailer in the checklist. */
    public function toggleRt(string $code): void
    {
        $this->rtCodes = in_array($code, $this->rtCodes, true)
            ? array_values(array_diff($this->rtCodes, [$code]))
            : [...$this->rtCodes, $code];
        $this->resetPage();
    }

    /** Resolve the pasted codes / names and tick the matches. */
    public function matchRetailers(FilterOptions $options): void
    {
        $tokens = preg_split('/[\r\n,;\t]+/', trim($this->rtPaste), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = $options->matchRetailers($tokens, $this->rdCode ?: null);

        $this->rtCodes = array_values(array_unique([...$this->rtCodes, ...$result['matched']]));
        $this->rtUnmatched = $result['unmatched'];
        $this->rtPaste = '';
        $this->resetPage();
    }

    public function clearRts(): void
    {
        $this->rtCodes = [];
        $this->rtUnmatched = [];
        $this->rtSearch = '';
        $this->resetPage();
    }

    public function export(string $format, ExportService $exports)
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);

        $exportType = $this->mode().'_'.$this->type; // stock_rd | sellout_rt | ...
        $filters = ReportFilters::fromArray([
            'rd_code' => $this->rdCode,
            'rt_codes' => $this->type === 'rt' ? $this->rtCodes : [],
            'lifecycle' => $this->lifecycle,
        ]);

        $exports->queue($exportType, $filters, auth()->id(), $format === 'xlsx' ? 'xlsx' : 'csv');
        session()->flash('status', ucfirst($this->mode()).' export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function render(StockReportService $stock, FilterOptions $options)
    {
        $rd = $this->rdCode ?: null;
        $rtCodes = $this->type === 'rt' ? array_values($this->rtCodes) : [];
        $stock->forLifecycle($this->lifecycle ?: null)->forMode($this->mode());

        $columns = ['models' => [], 'hasOther' => false, 'totals' => [], 'otherTotal' => 0, 'grandTotal' => 0];

        if ($this->type === 'model') {
            $rows = $stock->modelWise($rd, 50);
        } else {
            $columns = $stock->modelColumns($this->type, $rd, $rtCodes);
            $rows = $this->type === 'rt'
                ? $stock->rtWise($rd, $rtCodes, $columns['models'], 50)
                : $stock->rdWise($rd, $columns['models'], 50);
        }

        // Checklist: search results plus any selected code that falls outside them.
        $rtList = $options->retailers($rd, $this->rtSearch);
        $rtOptions = $rtList['options'];
        foreach ($this->rtCodes as $code) {
            if (! isset($rtOptions[$code])) {
                $rtOptions = [$code => $options->retailerLabel($code)] + $rtOptions;
            }
        }

        return view('livewire.stock-report', [
            'rows' => $rows,
            'columns' => $columns,
            'summary' => $stock->summary($rd, $rtCodes),
            'rdOptions' => $options->distributors(),
            'rtOptions' => $rtOptions,
            'rtTruncated' => $rtList['truncated'],
            'types' => static::TYPES,
            'noun' => $this->mode(),
        ]);
    }
}
