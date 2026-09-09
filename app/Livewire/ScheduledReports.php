<?php

namespace App\Livewire;

use App\Jobs\SendScheduledReportJob;
use App\Models\ScheduledReport;
use App\Support\RecordScope;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Scheduled Reports')]
class ScheduledReports extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $export_type = 'rt_report';

    public string $format = 'xlsx';

    public string $period = 'yesterday';

    public string $date_basis = 'st_date';

    public string $frequency = 'daily';

    public ?int $day_of_week = 1;

    public ?int $day_of_month = 1;

    public string $time = '07:00';

    public string $recipients = '';

    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('exports.create'), 403);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'export_type' => ['required', Rule::in(array_keys(config('reports.schedulable')))],
            'format' => ['required', 'in:csv,xlsx'],
            'period' => ['required', Rule::in(array_keys(config('reports.periods')))],
            'date_basis' => ['required', Rule::in(array_keys(config('reports.date_bases')))],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'integer', 'between:1,28'],
            'time' => ['required', 'date_format:H:i'],
            'recipients' => ['required', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return list<string> */
    private function parseRecipients(): array
    {
        $parts = preg_split('/[\s,;]+/', trim($this->recipients)) ?: [];

        return array_values(array_unique(array_filter(
            array_map('trim', $parts),
            fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL),
        )));
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'export_type', 'format', 'period', 'date_basis',
            'frequency', 'day_of_week', 'day_of_month', 'time', 'recipients', 'is_active',
        ]);
        $this->showForm = false;
    }

    public function edit(int $id): void
    {
        $r = ScheduledReport::findOrFail($id);
        $this->editingId = $r->id;
        $this->name = $r->name;
        $this->export_type = $r->export_type;
        $this->format = $r->format;
        $this->period = $r->period;
        $this->date_basis = $r->date_basis;
        $this->frequency = $r->frequency;
        $this->day_of_week = $r->day_of_week ?? 1;
        $this->day_of_month = $r->day_of_month ?? 1;
        $this->time = substr((string) $r->time, 0, 5);
        $this->recipients = implode(', ', (array) $r->recipients);
        $this->is_active = $r->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $emails = $this->parseRecipients();
        if ($emails === []) {
            $this->addError('recipients', 'Enter at least one valid email address.');

            return;
        }

        $dateable = (bool) (config("reports.schedulable.{$data['export_type']}.1") ?? false);

        $attributes = [
            'name' => $data['name'],
            'export_type' => $data['export_type'],
            'format' => $data['format'],
            'period' => $dateable ? $data['period'] : 'none',
            'date_basis' => $data['date_basis'],
            // Freeze the creator's RD row-scope so the emailed file stays scoped.
            'filters' => array_filter(['rd_scope' => RecordScope::rdCodes()]),
            'frequency' => $data['frequency'],
            'day_of_week' => $data['frequency'] === 'weekly' ? $data['day_of_week'] : null,
            'day_of_month' => $data['frequency'] === 'monthly' ? $data['day_of_month'] : null,
            'time' => $data['time'],
            'recipients' => $emails,
            'is_active' => $data['is_active'],
        ];

        if (! $this->editingId) {
            $attributes['created_by'] = auth()->id();
        }

        ScheduledReport::updateOrCreate(['id' => $this->editingId], $attributes);

        $this->resetForm();
        session()->flash('status', 'Scheduled report saved.');
    }

    public function toggle(int $id): void
    {
        $r = ScheduledReport::findOrFail($id);
        $r->update(['is_active' => ! $r->is_active]);
    }

    public function delete(int $id): void
    {
        ScheduledReport::whereKey($id)->delete();
        session()->flash('status', 'Scheduled report deleted.');
    }

    public function runNow(int $id): void
    {
        $r = ScheduledReport::findOrFail($id);
        SendScheduledReportJob::dispatch($r->id)->onQueue('exports');
        session()->flash('status', "\"{$r->name}\" queued — recipients will get it shortly.");
    }

    public function render()
    {
        return view('livewire.scheduled-reports', [
            'reports' => ScheduledReport::with('creator')->orderBy('name')->get(),
            'typeOptions' => config('reports.schedulable'),
            'periodOptions' => config('reports.periods'),
            'basisOptions' => config('reports.date_bases'),
            'currentDateable' => (bool) (config("reports.schedulable.{$this->export_type}.1") ?? false),
        ]);
    }
}
