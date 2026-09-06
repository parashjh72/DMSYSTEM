<?php

namespace App\Livewire;

use App\Enums\ImportStatus;
use App\Jobs\PrepareImportJob;
use App\Models\ImportBatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Import detail')]
class ImportDetail extends Component
{
    public string $uuid;

    public function mount(ImportBatch $batch): void
    {
        abort_unless(auth()->user()?->can('imports.view'), 403);
        $this->uuid = $batch->uuid;
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

    public function batch(): ImportBatch
    {
        return ImportBatch::where('uuid', $this->uuid)->firstOrFail();
    }

    public function render()
    {
        $batch = $this->batch();

        return view('livewire.import-detail', [
            'batch' => $batch,
            'polling' => ! $batch->status->isTerminal(),
            'errorSample' => $batch->rowErrors()->latest('id')->limit(50)->get(),
            'errorCounts' => $batch->rowErrors()
                ->selectRaw('error_type, COUNT(*) c')->groupBy('error_type')->pluck('c', 'error_type'),
        ]);
    }
}
