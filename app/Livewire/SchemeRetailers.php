<?php

namespace App\Livewire;

use App\Models\Scheme;
use App\Models\SchemeRetailer;
use App\Services\Reporting\FilterOptions;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Scheme retailers')]
class SchemeRetailers extends Component
{
    public string $uuid;

    // defaults applied to newly added retailers
    public string $defaultPlan = 'option_one';

    public string $defaultCategory = 'other';

    public string $search = '';

    public string $paste = '';

    public array $notMatched = [];

    public function mount(Scheme $scheme): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        $this->uuid = $scheme->uuid;
    }

    private function scheme(): Scheme
    {
        return Scheme::where('uuid', $this->uuid)->firstOrFail();
    }

    public function add(string $rtCode): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        $rt = DB::table('retailers')->where('code', $rtCode)->first();
        if (! $rt) {
            return;
        }

        SchemeRetailer::updateOrCreate(
            ['scheme_id' => $this->scheme()->id, 'rt_code' => $rt->code],
            [
                'rt_name' => $rt->name,
                'plan' => $this->defaultPlan,
                'category' => $this->defaultCategory,
                'added_by' => auth()->id(),
            ],
        );
        $this->search = '';
    }

    public function matchAndAdd(FilterOptions $options): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        $tokens = preg_split('/[\r\n,;\t]+/', trim($this->paste), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = $options->matchRetailers($tokens);

        foreach ($result['matched'] as $code) {
            $this->add($code);
        }
        $this->notMatched = $result['unmatched'];
        $this->paste = '';
    }

    public function updateRow(int $id, string $field, string $value): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        if (! in_array($field, ['plan', 'category', 'note'], true)) {
            return;
        }
        SchemeRetailer::whereKey($id)->update([$field => $value ?: ($field === 'note' ? null : 'other')]);
    }

    public function updateMinSlab(int $id, ?string $value): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        SchemeRetailer::whereKey($id)->update(['min_slab' => $value === '' || $value === null ? null : (int) $value]);
    }

    public function remove(int $id): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        SchemeRetailer::whereKey($id)->delete();
    }

    public function render(FilterOptions $options)
    {
        $scheme = $this->scheme();

        return view('livewire.scheme-retailers', [
            'scheme' => $scheme,
            'enrolled' => $scheme->retailers()->orderBy('rt_code')->get(),
            'searchResults' => $this->search !== ''
                ? $options->retailers(null, $this->search)['options']
                : [],
            'plans' => config('schemes.plans'),
            'categories' => collect(config('schemes.categories'))->map(fn ($c) => $c['label']),
            'catMinSlab' => collect(config('schemes.categories'))->map(fn ($c) => $c['min_slab']),
        ]);
    }
}
