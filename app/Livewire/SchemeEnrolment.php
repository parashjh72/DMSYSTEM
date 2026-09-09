<?php

namespace App\Livewire;

use App\Models\Scheme;
use App\Models\SchemeRetailer;
use App\Services\Reporting\FilterOptions;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * RT-centric view of scheme enrolment: pick a retailer, see every scheme it is
 * (or was) enrolled in, enrol it into another, deactivate an enrolment.
 * Retailers always come from the existing master — never created here.
 */
#[Layout('components.layouts.app')]
#[Title('Scheme Enrolment')]
class SchemeEnrolment extends Component
{
    public string $search = '';

    #[Url]
    public string $rtCode = '';

    public string $enrolSchemeUuid = '';

    public string $plan = 'option_one';

    public string $category = 'other';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('schemes.enrol'), 403);
    }

    public function pick(string $code): void
    {
        $rt = DB::table('retailers')->where('code', $code)->first();
        if ($rt) {
            $this->rtCode = $rt->code;
            $this->search = '';
        }
    }

    public function enrol(): void
    {
        abort_unless(auth()->user()?->can('schemes.enrol'), 403);
        $this->validate([
            'rtCode' => ['required', 'string'],
            'enrolSchemeUuid' => ['required', 'string'],
        ]);

        $rt = DB::table('retailers')->where('code', $this->rtCode)->first();
        $scheme = Scheme::where('uuid', $this->enrolSchemeUuid)->first();
        if (! $rt || ! $scheme) {
            $this->addError('enrolSchemeUuid', 'Pick a retailer and a scheme.');

            return;
        }

        $existing = SchemeRetailer::where('scheme_id', $scheme->id)->where('rt_code', $rt->code)->first();
        if ($existing && $existing->isActive()) {
            $this->addError('enrolSchemeUuid', 'This retailer is already actively enrolled in that scheme.');

            return;
        }

        SchemeRetailer::updateOrCreate(
            ['scheme_id' => $scheme->id, 'rt_code' => $rt->code],
            [
                'rt_name' => $rt->name,
                'enrolled_on' => now()->toDateString(),
                'effective_from' => $scheme->effective_from,
                'effective_to' => $scheme->effective_to,
                'status' => 'active',
                'deactivated_at' => null,
                'deactivated_by' => null,
                'plan' => $this->plan,
                'category' => $this->category,
                'added_by' => auth()->id(),
            ],
        );

        $this->reset('enrolSchemeUuid');
        session()->flash('status', "Enrolled {$rt->code} into {$scheme->name}.");
    }

    public function deactivate(int $id): void
    {
        abort_unless(auth()->user()?->can('schemes.enrol'), 403);
        SchemeRetailer::whereKey($id)->update([
            'status' => 'inactive', 'deactivated_at' => now(), 'deactivated_by' => auth()->id(),
        ]);
    }

    public function reactivate(int $id): void
    {
        abort_unless(auth()->user()?->can('schemes.enrol'), 403);
        SchemeRetailer::whereKey($id)->update([
            'status' => 'active', 'deactivated_at' => null, 'deactivated_by' => null,
        ]);
    }

    public function render(FilterOptions $options)
    {
        $rt = $this->rtCode ? DB::table('retailers')->where('code', $this->rtCode)->first() : null;

        $enrolments = $rt
            ? SchemeRetailer::with('scheme')->where('rt_code', $rt->code)->latest('id')->get()
            : collect();

        $enrolledSchemeIds = $enrolments->where('status', 'active')->pluck('scheme_id')->all();

        return view('livewire.scheme-enrolment', [
            'rt' => $rt,
            'enrolments' => $enrolments,
            'searchResults' => $this->search !== '' ? $options->retailers(null, $this->search)['options'] : [],
            'schemeOptions' => Scheme::whereNotIn('id', $enrolledSchemeIds)
                ->orderByDesc('effective_from')->get(['uuid', 'name', 'effective_from', 'effective_to', 'status']),
            'plans' => config('schemes.plans'),
            'categories' => collect(config('schemes.categories'))->map(fn ($c) => $c['label']),
        ]);
    }
}
