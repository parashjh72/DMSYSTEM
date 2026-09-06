<?php

namespace App\Livewire;

use App\Models\SalesActivationRecord;
use App\Support\Imei;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('IMEI Search')]
class ImeiSearch extends Component
{
    #[Url]
    public string $q = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function render()
    {
        $record = null;
        $searched = false;

        if (($clean = Imei::clean($this->q)) !== '') {
            $searched = true;
            // Exact, indexed unique lookup — near-instant regardless of table size.
            $record = SalesActivationRecord::with(['firstImportBatch', 'lastImportBatch'])
                ->where('imei', $clean)
                ->first();
        }

        return view('livewire.imei-search', compact('record', 'searched'));
    }
}
