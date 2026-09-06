<?php

namespace App\Livewire;

use App\Services\Export\ExportService;
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

    /** report key => [label, service method, export type] */
    public const TYPES = [
        'rd' => ['RD-wise', 'rdWise', 'rd_report'],
        'rt' => ['RT-wise', 'rtWise', 'rt_report'],
        'tso' => ['TSO-wise', 'tsoWise', 'tso_report'],
        'model' => ['Model-wise', 'modelWise', 'model_report'],
        'date' => ['Date-wise (ST)', 'dateWise', 'date_report'],
        'activation' => ['Activation', 'activationWise', null],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
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

    public function render(ReportService $reports)
    {
        $method = self::TYPES[$this->type][1];
        $rows = $reports->{$method}(ReportFilters::fromArray($this->f), 50);

        return view('livewire.reports', [
            'rows' => $rows,
            'lag' => $reports->lagDistribution(ReportFilters::fromArray($this->f)),
        ]);
    }
}
