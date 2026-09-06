<?php

namespace App\Livewire;

use App\Models\SalesActivationRecord;
use App\Support\Imei;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('IMEI Search')]
class ImeiSearch extends Component
{
    /** Raw pasted text — one IMEI per line, or space / comma / semicolon separated. */
    public string $imeis = '';

    /** Upper bound on how many IMEIs one search will look up. */
    public const MAX = 2000;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function clear(): void
    {
        $this->imeis = '';
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

        $capped = count($wanted) > self::MAX;
        $wanted = array_slice($wanted, 0, self::MAX);

        $records = collect();
        if ($wanted !== []) {
            $records = SalesActivationRecord::query()
                ->whereIn('imei', $wanted)          // exact, indexed unique lookup
                ->orderBy('imei')
                ->get();
        }

        $found = $records->pluck('imei')->all();
        $unmatched = array_values(array_diff($wanted, $found));

        return view('livewire.imei-search', [
            'records' => $records,
            'searched' => $wanted !== [],
            'totalWanted' => count($wanted),
            'foundCount' => count($found),
            'unmatched' => $unmatched,
            'capped' => $capped,
        ]);
    }
}
