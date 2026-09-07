<?php

namespace App\Livewire;

use App\Enums\ImportStatus;
use App\Jobs\PrepareImportJob;
use App\Models\ImportBatch;
use App\Services\Export\ExportService;
use App\Services\Reporting\ReportFilters;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Import detail')]
class ImportDetail extends Component
{
    use WithPagination;

    public string $uuid;

    /** rows | errors — which table is shown */
    #[Url]
    public string $tab = 'rows';

    public function mount(ImportBatch $batch): void
    {
        abort_unless(auth()->user()?->can('imports.view'), 403);
        $this->uuid = $batch->uuid;
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function retry(): void
    {
        abort_unless(auth()->user()?->can('imports.create'), 403);
        $batch = $this->batch();

        $batch->chunks()->where('status', 'failed')->update(['status' => 'pending']);
        $batch->update(['status' => ImportStatus::Queued, 'error_message' => null, 'completed_at' => null]);

        PrepareImportJob::dispatch($batch->uuid)->onQueue(config('import.queues.prepare'));
        session()->flash('status', 'Re-queued. Failed chunks will be reprocessed.');
    }

    public function exportRows(ExportService $exports)
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);

        $exports->queue('records', ReportFilters::fromArray([
            'import_batch' => $this->batch()->id,
        ]), auth()->id());

        session()->flash('status', 'Export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function exportErrors(ExportService $exports)
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);

        $exports->queue('import_errors', ReportFilters::fromArray([
            'import_batch' => $this->batch()->id,
        ]), auth()->id());

        session()->flash('status', 'Error list export queued — track it on the Exports page.');
        $this->redirectRoute('exports.index', navigate: true);
    }

    public function batch(): ImportBatch
    {
        return ImportBatch::where('uuid', $this->uuid)->firstOrFail();
    }

    public function render()
    {
        $batch = $this->batch();

        $rows = $this->tab === 'rows'
            ? DB::table('sales_activation_records')
                ->where('last_import_batch_id', $batch->id)
                ->orderBy('id')
                ->paginate(50)
            : null;

        $errors = $this->tab === 'errors'
            ? $batch->rowErrors()->orderBy('id')->paginate(50)
            : null;

        return view('livewire.import-detail', [
            'batch' => $batch,
            'polling' => ! $batch->status->isTerminal(),
            'rows' => $rows,
            'errors' => $errors,
            'errorCounts' => $batch->rowErrors()
                ->selectRaw('error_type, COUNT(*) c')->groupBy('error_type')->pluck('c', 'error_type'),
        ]);
    }
}
