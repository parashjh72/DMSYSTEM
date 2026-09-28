<?php

namespace App\Livewire;

use App\Models\DeviceAudit;
use App\Models\SalesActivationRecord;
use App\Services\DeviceEditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Edit Device')]
class DeviceEdit extends Component
{
    public SalesActivationRecord $record;

    public string $tso = '';

    public string $rd_code = '';

    public string $rt_code = '';

    public string $st_date = '';

    public string $activation_date = '';

    public string $reason = '';

    public function mount(string $imei): void
    {
        abort_unless(auth()->user()?->can('devices.edit'), 403);

        $this->record = SalesActivationRecord::where('imei', $imei)->firstOrFail();
        $this->fillFromRecord();
    }

    public function save(DeviceEditService $service): void
    {
        abort_unless(auth()->user()?->can('devices.edit'), 403);

        $this->validate([
            'tso' => ['nullable', 'string', 'max:120'],
            // Codes must exist in Master Data only when changed: legacy devices may carry unknown codes.
            'rd_code' => ['nullable', 'string', 'max:40', ...$this->changed('rd_code') ? [Rule::exists('retail_distributors', 'code')] : []],
            'rt_code' => ['nullable', 'string', 'max:40', ...$this->changed('rt_code') ? [Rule::exists('retailers', 'code')] : []],
            'st_date' => ['nullable', 'date_format:Y-m-d'],
            'activation_date' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rd_code.exists' => 'This RD code is not in Master Data > Distributors.',
            'rt_code.exists' => 'This RT code is not in Master Data > Retailers.',
            'reason.required' => 'Say why you are changing this device — it is saved in the audit log.',
        ]);

        try {
            $changed = $service->update($this->record, [
                'tso' => $this->tso,
                'rd_code' => $this->rd_code,
                'rt_code' => $this->rt_code,
                'st_date' => $this->st_date,
                'activation_date' => $this->activation_date,
            ], $this->reason, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->record->refresh();
        $this->fillFromRecord();
        $this->reason = '';
        session()->flash('status', $changed === 0 ? 'Nothing changed.' : "Saved {$changed} change(s) to the audit log.");
    }

    private function changed(string $field): bool
    {
        return trim($this->{$field}) !== (string) $this->record->{$field};
    }

    private function fillFromRecord(): void
    {
        $this->tso = (string) $this->record->tso;
        $this->rd_code = (string) $this->record->rd_code;
        $this->rt_code = (string) $this->record->rt_code;
        $this->st_date = (string) $this->record->st_date?->toDateString();
        $this->activation_date = (string) $this->record->activation_date?->toDateString();
    }

    public function render()
    {
        return view('livewire.device-edit', [
            'audits' => DeviceAudit::with('user:id,name')
                ->where('imei', $this->record->imei)
                ->latest('id')
                ->limit(100)
                ->get(),
            'tsoOptions' => DB::table('territory_officers')->orderBy('name')->pluck('name'),
            'rdOptions' => DB::table('retail_distributors')->orderBy('code')->get(['code', 'name']),
            'rtOptions' => DB::table('retailers')->orderBy('code')->get(['code', 'name']),
        ]);
    }
}
