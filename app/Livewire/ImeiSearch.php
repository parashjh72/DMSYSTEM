<?php

namespace App\Livewire;

use App\Models\SalesActivationRecord;
use App\Support\Imei;
use App\Support\RecordScope;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('IMEI Search')]
class ImeiSearch extends Component
{
    use WithPagination;

    /** Raw pasted text — one IMEI per line, or space / comma / semicolon separated. */
    public string $imeis = '';

    /** Rows shown per page of results. */
    public int $perPage = 100;

    /** Upper bound on how many IMEIs one search will look up (config/import.php). */
    public function max(): int
    {
        return (int) config('import.imei_search_max', 20000);
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function clear(): void
    {
        $this->imeis = '';
        $this->resetPage();
    }

    public function updatedImeis(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tokens = preg_split('/[\s,;]+/', trim($this->imeis), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // clean + dedupe, preserving first-seen order
        $wanted = [];
        foreach ($tokens as $token) {
            $clean = Imei::clean($token);
            if ($clean !== '' && ! in_array($clean, $wanted, true)) {
                $wanted[] = $clean;
            }
        }

        $capped = count($wanted) > $this->max();
        $wanted = array_slice($wanted, 0, $this->max());

        $records = null;
        $found = [];

        if ($wanted !== []) {
            $scope = RecordScope::rdCodes();
            $scoped = fn () => SalesActivationRecord::query()
                ->when($scope, fn ($q, $c) => $q->whereIn('rd_code', $c))
                ->whereIn('imei', $wanted);

            // Full found set — just the imei column, one indexed lookup, cheap even at 20k.
            $found = $scoped()->pluck('imei')->all();

            // Displayed page only.
            $records = $scoped()
                ->orderBy('imei')
                ->paginate(min(max($this->perPage, 25), 500));
        }

        $unmatched = array_values(array_diff($wanted, $found));

        return view('livewire.imei-search', [
            'records' => $records,
            'searched' => $wanted !== [],
            'totalWanted' => count($wanted),
            'foundCount' => count($found),
            'unmatched' => $unmatched,
            'capped' => $capped,
            'maxImeis' => $this->max(),
        ]);
    }
}
