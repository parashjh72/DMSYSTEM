<?php

namespace App\Livewire;

use App\Enums\ImportMode;
use App\Models\ImportBatch;
use App\Services\Import\ImportService;
use App\Services\Import\SpreadsheetReader;
use App\Support\HeaderMap;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Imports')]
class ImportManager extends Component
{
    use WithFileUploads, WithPagination;

    public $file;

    /** @var array{uuid:string,headers:array,map:array,unmatched:array,missing:array}|null */
    public ?array $review = null;

    public string $mode = 'upsert';

    public int $chunkSize = 5000;

    /** records | sell_through | activation */
    public string $kind = 'records';

    public function updatedFile(): void
    {
        $this->authorizePermission();
        $this->validate([
            'file' => ['required', 'file', 'max:'.config('import.max_upload_kb'), 'extensions:csv,txt,xlsx'],
        ], [
            'file.max' => 'File exceeds the web upload limit ('.round(config('import.max_upload_kb') / 1024).' MB). '
                .'For larger files use: php artisan records:import <path>',
        ]);

        $kind = in_array($this->kind, ['sell_through', 'activation'], true) ? $this->kind : 'records';
        $batch = app(ImportService::class)->createFromUpload($this->file, auth()->id(), $kind);

        $path = Storage::disk($batch->disk)->path($batch->stored_path);
        $reader = new SpreadsheetReader($path, $batch->file_type);
        $headers = $reader->headers();
        [$aliases, $required] = ImportService::schemaFor($kind);
        $resolved = HeaderMap::resolve($headers, $aliases, $required);

        $this->review = [
            'uuid' => $batch->uuid,
            'kind' => $batch->kind,
            'headers' => $headers,
            'map' => $resolved['map'],
            'unmatched' => $resolved['unmatched'],
            'missing' => $resolved['missing'],
        ];
        $this->chunkSize = (int) $batch->chunk_size;
    }

    public function setMapping(string $field, string $columnIndex): void
    {
        if ($columnIndex === '') {
            unset($this->review['map'][$field]);
        } else {
            $this->review['map'][$field] = (int) $columnIndex;
        }
    }

    public function startImport(): void
    {
        $this->authorizePermission();

        $batch = ImportBatch::where('uuid', $this->review['uuid'])->firstOrFail();

        app(ImportService::class)->start(
            $batch,
            columnMap: array_map('intval', $this->review['map']),
            mode: ImportMode::from($this->mode),
            chunkSize: $this->chunkSize,
        );

        $this->reset('file', 'review');
        session()->flash('status', "Import queued for {$batch->original_filename}.");
        $this->redirectRoute('imports.show', ['batch' => $batch->uuid], navigate: true);
    }

    public function cancelReview(): void
    {
        if ($this->review) {
            ImportBatch::where('uuid', $this->review['uuid'])->delete();
        }
        $this->reset('file', 'review');
    }

    private function authorizePermission(): void
    {
        abort_unless(auth()->user()?->can('imports.create'), 403);
    }

    public function render()
    {
        $reviewKind = $this->review['kind'] ?? $this->kind;
        [$aliases, $required] = ImportService::schemaFor($reviewKind);

        return view('livewire.import-manager', [
            'fields' => array_keys($aliases),
            'requiredFields' => $required,
            'batches' => ImportBatch::with('creator')->latest()->paginate(15),
        ]);
    }
}
