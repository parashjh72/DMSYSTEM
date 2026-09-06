<?php

namespace App\Livewire;

use App\Models\DeviceModel;
use App\Support\ModelClassifier;
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

    /** Models tab only: '', 'running', 'out'. */
    #[Url]
    public string $modelStatus = '';

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
        $this->modelStatus = '';
    }

    public function updatedModelStatus(): void
    {
        $this->resetPage();
    }

    /** Flip a single model between running / out. */
    public function toggleModelStatus(int $id): void
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);

        $model = DeviceModel::findOrFail($id);
        $model->update(['status' => $model->status === 'running' ? 'out' : 'running']);
    }

    /** Re-apply config/models.php running-series rules to every model. */
    public function reclassify(): void
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);

        $counts = ModelClassifier::applyAll();
        session()->flash('status', "Re-classified: {$counts['running']} running, {$counts['out']} out.");
    }

    public function render()
    {
        [$label, $table, $columns] = self::TABS[$this->tab] ?? self::TABS['rd'];
        $isModels = $this->tab === 'model';

        $query = DB::table($table);

        if ($this->search !== '') {
            $query->where(function ($q) use ($columns) {
                foreach ($columns as $c) {
                    $q->orWhere($c, 'like', $this->search.'%');
                }
            });
        }

        if ($isModels && in_array($this->modelStatus, ['running', 'out'], true)) {
            $query->where('status', $this->modelStatus);
        }

        $counts = $isModels
            ? DB::table('device_models')->selectRaw("
                COUNT(*) total,
                SUM(status = 'running') running,
                SUM(status = 'out') `out`
              ")->first()
            : null;

        return view('livewire.master-data', [
            'tabs' => self::TABS,
            'columns' => $columns,
            'isModels' => $isModels,
            'counts' => $counts,
            'rows' => $query->orderBy($columns[0])->paginate(30),
        ]);
    }
}
