<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Master Data')]
class MasterData extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'rd';

    #[Url]
    public string $search = '';

    private const TABS = [
        'rd' => ['Distributors', 'retail_distributors', ['code', 'name']],
        'rt' => ['Retailers', 'retailers', ['code', 'name', 'rd_code']],
        'model' => ['Models', 'device_models', ['name']],
        'tso' => ['TSOs', 'territory_officers', ['name']],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->search = '';
    }

    public function render()
    {
        [$label, $table, $columns] = self::TABS[$this->tab] ?? self::TABS['rd'];

        $query = DB::table($table);
        if ($this->search !== '') {
            $query->where(function ($q) use ($columns) {
                foreach ($columns as $c) {
                    $q->orWhere($c, 'like', $this->search.'%');
                }
            });
        }

        return view('livewire.master-data', [
            'tabs' => self::TABS,
            'columns' => $columns,
            'rows' => $query->orderBy($columns[0])->paginate(30),
        ]);
    }
}
