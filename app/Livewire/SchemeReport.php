<?php

namespace App\Livewire;

use App\Models\Scheme;
use App\Services\Export\ExportService;
use App\Services\Reporting\FilterOptions;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\SchemeService;
use App\Support\RecordScope;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Scheme achievement')]
class SchemeReport extends Component
{
    use WithPagination;

    public string $uuid;

    #[Url]
    public ?string $rdCode = null;

    #[Url]
    public bool $qualifiedOnly = true;   // hide retailers below slab 1

    #[Url]
    public bool $enrolledOnly = false;   // only manually-enrolled retailers

    public function mount(Scheme $scheme): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
        // Scheme achievement spans all territories — not for TSO-scoped users.
        abort_if(RecordScope::restricted(), 403);
        $this->uuid = $scheme->uuid;
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function export(ExportService $exports)
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);
        $exports->queue('scheme_achievement', ReportFilters::fromArray(array_filter([
            'scheme_uuid' => $this->uuid,
            'rd_code' => $this->rdCode,
            'scheme_enrolled_only' => $this->enrolledOnly ? '1' : null,
        ])), auth()->id(), 'xlsx');
        session()->flash('status', 'Export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function render(SchemeService $service, FilterOptions $options)
    {
        $scheme = Scheme::with('slabs')->where('uuid', $this->uuid)->firstOrFail();

        $rows = $service->achievement($scheme, $this->rdCode ?: null, $this->enrolledOnly);
        if ($this->qualifiedOnly) {
            $rows = $rows->filter(fn ($r) => $r['slab_no'] !== null)->values();
        }

        return view('livewire.scheme-report', [
            'scheme' => $scheme,
            'rows' => $rows,
            'totalPayout' => $rows->sum('payout_amount'),
            'totalValue' => $rows->sum('qualified_value'),
            'qualifiedCount' => $rows->whereNotNull('slab_no')->count(),
            'eligibleCount' => $rows->where('eligible', true)->count(),
            'enrolledCount' => $scheme->retailers()->count(),
            'rdOptions' => $options->distributors(),
        ]);
    }
}
