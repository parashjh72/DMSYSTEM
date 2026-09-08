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

    /** Admins see every export; everyone else only their own. */
    private function scopeToOwner($query)
    {
        return $query->when(
            ! auth()->user()?->can('settings.manage'),
            fn ($q) => $q->where('created_by', auth()->id()),
        );
    }

    public function download(string $uuid)
    {
        $export = $this->scopeToOwner(ExportJob::where('uuid', $uuid))->firstOrFail();
        abort_unless($export->status === 'completed' && $export->stored_path, 404);

        return Storage::disk($export->disk)->download(
            $export->stored_path,
            str($export->type)->slug().'-'.$export->created_at->format('Ymd-His').'.'.($export->format === 'xlsx' ? 'xlsx' : 'csv'),
        );
    }

    public function render()
    {
        return view('livewire.export-manager', [
            'exports' => $this->scopeToOwner(ExportJob::with('creator'))->latest()->paginate(20),
            'polling' => $this->scopeToOwner(ExportJob::whereIn('status', ['pending', 'processing']))->exists(),
        ]);
    }
}
