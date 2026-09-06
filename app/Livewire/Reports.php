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

    /** Which single date the report / filters / presets run against. */
    #[Url]
    public string $dateBasis = 'st';

    /** report key => [label, service method, export type] */
    public const TYPES = [
        'rd' => ['RD-wise', 'rdWise', 'rd_report'],
        'rt' => ['RT-wise', 'rtWise', 'rt_report'],
        'tso' => ['TSO-wise', 'tsoWise', 'tso_report'],
        'model' => ['Model-wise', 'modelWise', 'model_report'],
        'date' => ['Date-wise (ST)', 'dateWise', 'date_report'],
        'activation' => ['Activation', 'activationWise', null],
    ];

    /** Allowed date bases per report type. First entry is the default. */
    public const DATE_BASES = [
        'rd' => ['st', 'activation', 'sell_in'],
        'rt' => ['st', 'activation'],
        'tso' => ['st', 'activation'],
        'model' => ['st', 'activation'],
        'date' => ['st'],
        'activation' => ['activation'],
    ];

    /** basis => [label, filter key prefix]. */
    public const BASIS_META = [
        'st' => ['ST date', 'st_date'],
        'activation' => ['Activation date', 'activation_date'],
        'sell_in' => ['Sell-In date', 'sell_in_date'],
    ];

    public function mount(FilterOptions $options): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);

        $this->normalizeDateBasis(clearDates: false);

        // Restore the retailer combobox text when arriving with ?f[rt_code]=...
        if (($code = $this->f['rt_code'] ?? null) && $this->rtSearch === '') {
            $this->rtSearch = $options->retailerLabel($code);
        }
    }

    /** @return list<string> */
    public function allowedBases(): array
    {
        return self::DATE_BASES[$this->type] ?? ['st', 'activation'];
    }

    public function updatedType(): void
    {
        $this->normalizeDateBasis();
        $this->resetPage();
    }

    public function setDateBasis(string $basis): void
    {
        if (in_array($basis, $this->allowedBases(), true)) {
            $this->dateBasis = $basis;
            $this->clearDateFilters();
            $this->activePreset = null;
            $this->resetPage();
        }
    }

    /** "Report by: … Inactive" — toggle the sold-but-not-activated filter. */
    public function toggleInactive(): void
    {
        $this->f['activation_status'] = ($this->f['activation_status'] ?? '') === 'not_activated'
            ? null
            : 'not_activated';
        $this->resetPage();
    }

    /** Keep $dateBasis valid for the current report type; optionally drop stale ranges. */
    private function normalizeDateBasis(bool $clearDates = true): void
    {
        if (! in_array($this->dateBasis, $this->allowedBases(), true)) {
            $this->dateBasis = $this->allowedBases()[0];
            if ($clearDates) {
                $this->clearDateFilters();
                $this->activePreset = null;
            }
        }
    }

    private function clearDateFilters(): void
    {
        foreach (self::BASIS_META as [, $prefix]) {
            unset($this->f["{$prefix}_from"], $this->f["{$prefix}_to"]);
        }
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
        $this->dateBasis = $this->allowedBases()[0];
        $this->resetPage();
    }

    /** Quick date-range presets — applied to whichever single date basis is active. */
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

        $this->clearDateFilters();
        $prefix = self::BASIS_META[$this->dateBasis][1];
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

        [$basisLabel, $basisPrefix] = self::BASIS_META[$this->dateBasis];

        return view('livewire.reports', [
            'rows' => $reports->{$method}($filters, 50),
            'lag' => $reports->lagDistribution($filters),
            'tsoOptions' => $options->tso(),
            'modelOptions' => $options->models(),
            'rdOptions' => $options->distributors(),
            'rtOptions' => $rtOptions,
            'rtTruncated' => $rt['truncated'],
            'bases' => $this->allowedBases(),
            'basisLabel' => $basisLabel,
            'basisPrefix' => $basisPrefix,
        ]);
    }
}
