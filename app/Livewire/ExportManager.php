<?php

namespace App\Livewire;

use App\Models\ExportJob;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Exports')]
class ExportManager extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('exports.view'), 403);
    }

    public function download(string $uuid)
    {
        $export = ExportJob::where('uuid', $uuid)->firstOrFail();
        abort_unless($export->status === 'completed' && $export->stored_path, 404);

        return Storage::disk($export->disk)->download(
            $export->stored_path,
            str($export->type)->slug().'-'.$export->created_at->format('Ymd-His').'.'.($export->format === 'xlsx' ? 'xlsx' : 'csv'),
        );
    }

    public function render()
    {
        return view('livewire.export-manager', [
            'exports' => ExportJob::with('creator')->latest()->paginate(20),
            'polling' => ExportJob::whereIn('status', ['pending', 'processing'])->exists(),
        ]);
    }
}
