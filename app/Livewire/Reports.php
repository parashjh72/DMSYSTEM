<?php

namespace App\Livewire;

use App\Services\Export\ExportService;
use App\Services\Reporting\FilterOptions;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Reports')]
class Reports extends Component
{
    use WithPagination;

    #[Url]
    public string $type = 'rd';

    #[Url]
    public array $f = [];

    public ?string $activePreset = null;

    /** Typeahead query for the retailer dropdown. */
    public string $rtSearch = '';

    /** report key => [label, service method, export type] */
    public const TYPES = [
        'rd' => ['RD-wise', 'rdWise', 'rd_report'],
        'rt' => ['RT-wise', 'rtWise', 'rt_report'],
        'tso' => ['TSO-wise', 'tsoWise', 'tso_report'],
        'model' => ['Model-wise', 'modelWise', 'model_report'],
        'date' => ['Date-wise (ST)', 'dateWise', 'date_report'],
        'activation' => ['Activation', 'activationWise', null],
    ];

    public function mount(FilterOptions $options): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        // Restore the retailer combobox text when arriving with ?f[rt_code]=...
        if (($code = $this->f['rt_code'] ?? null) && $this->rtSearch === '') {
            $this->rtSearch = $options->retailerLabel($code);
        }
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->f = [];
        $this->activePreset = null;
        $this->rtSearch = '';
        $this->resetPage();
    }

    /** Quick date-range presets. `date` reports filter on activation_date, everything else on st_date. */
    public function datePreset(string $preset): void
    {
        $now = now();

        [$from, $to] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            default => [null, null],
        };

        $prefix = $this->type === 'activation' ? 'activation_date' : 'st_date';

        $this->f["{$prefix}_from"] = $from?->toDateString();
        $this->f["{$prefix}_to"] = $to?->toDateString();
        $this->activePreset = $preset;
        $this->resetPage();
    }

    public function export(ExportService $exports)
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);
        $exportType = self::TYPES[$this->type][2] ?? null;
        abort_if($exportType === null, 422, 'This report is not exportable yet.');

        $exports->queue($exportType, ReportFilters::fromArray($this->f), auth()->id());
        session()->flash('status', 'Export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function updated(string $property): void
    {
        // Choosing a distributor resets the retailer.
        if ($property === 'f.rd_code') {
            $this->f['rt_code'] = null;
            $this->rtSearch = '';
            $this->resetPage();
        }

        // Typing in the retailer box invalidates a prior pick until one is re-chosen.
        if ($property === 'rtSearch' && ($this->f['rt_code'] ?? null)) {
            $this->f['rt_code'] = null;
            $this->resetPage();
        }
    }

    /** Pick a retailer from the combobox dropdown ('' = clear). */
    public function selectRt(string $code, FilterOptions $options): void
    {
        if ($code === '') {
            $this->f['rt_code'] = null;
            $this->rtSearch = '';
        } else {
            $this->f['rt_code'] = $code;
            $this->rtSearch = $options->retailerLabel($code);
        }
        $this->resetPage();
    }

    public function render(ReportService $reports, FilterOptions $options)
    {
        $method = self::TYPES[$this->type][1];
        $filters = ReportFilters::fromArray($this->f);

        $selectedRt = $this->f['rt_code'] ?? null;

        // When the box still shows the chosen retailer's label, treat it as "no
        // query" so the dropdown offers the full list to switch from.
        $query = ($selectedRt && $this->rtSearch === $options->retailerLabel($selectedRt))
            ? null
            : $this->rtSearch;

        $rt = $options->retailers($this->f['rd_code'] ?? null, $query);
        $rtOptions = $rt['options'];

        if ($selectedRt && ! isset($rtOptions[$selectedRt])) {
            $rtOptions = [$selectedRt => $options->retailerLabel($selectedRt)] + $rtOptions;
        }

        return view('livewire.reports', [
            'rows' => $reports->{$method}($filters, 50),
            'lag' => $reports->lagDistribution($filters),
            'tsoOptions' => $options->tso(),
            'modelOptions' => $options->models(),
            'rdOptions' => $options->distributors(),
            'rtOptions' => $rtOptions,
            'rtTruncated' => $rt['truncated'],
        ]);
    }
}
