<?php

namespace App\Livewire;

use App\Models\DeviceModel;
use App\Services\Reporting\FilterOptions;
use App\Support\ModelClassifier;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

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

    /** Retailers tab only: filter by distributor code. */
    #[Url]
    public string $rdFilter = '';

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
        $this->rdFilter = '';
    }

    public function updatedModelStatus(): void
    {
        $this->resetPage();
    }

    public function updatedRdFilter(): void
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

    /** @return array{0: string, 1: list<string>, 2: Builder} [tab, columns, filtered query] */
    private function tabQuery(): array
    {
        [$label, $table, $columns] = self::TABS[$this->tab] ?? self::TABS['rd'];
        if ($this->tab === 'model') {
            $columns = ['name', 'status'];
        }

        $query = DB::table($table);

        if ($this->search !== '') {
            $query->where(function ($q) use ($columns) {
                foreach ($columns as $c) {
                    $q->orWhere($c, 'like', $this->search.'%');
                }
            });
        }
        if ($this->tab === 'model' && in_array($this->modelStatus, ['running', 'out'], true)) {
            $query->where('status', $this->modelStatus);
        }
        if ($this->tab === 'rt' && $this->rdFilter !== '') {
            $query->where('rd_code', $this->rdFilter);
        }

        return [$this->tab, $columns, $query];
    }

    /** Direct download of the current tab (with filters), master tables are small. */
    public function export(string $format)
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);

        [$tab, $columns, $query] = $this->tabQuery();
        $rows = $query->orderBy($columns[0])->get();
        $header = array_map(fn ($c) => str_replace('_', ' ', ucfirst($c)), $columns);
        $name = 'master-'.$tab.'-'.now()->format('Ymd-His');

        if ($format === 'xlsx') {
            $tmp = tempnam(sys_get_temp_dir(), 'md').'.xlsx';
            $writer = new Writer;
            $writer->openToFile($tmp);
            $headStyle = (new Style)
                ->withFontBold(true)->withFontColor(Color::WHITE)
                ->withBackgroundColor(Color::DARK_BLUE);
            $writer->addRow(Row::fromValuesWithStyle($header, $headStyle));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(
                    array_map(fn ($c) => $row->$c ?? '', $columns),
                ));
            }
            $writer->close();

            return response()->download($tmp, "{$name}.xlsx")->deleteFileAfterSend();
        }

        return response()->streamDownload(function () use ($header, $rows, $columns) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => $row->$c ?? '', $columns), ',', '"', '');
            }
            fclose($out);
        }, "{$name}.csv", ['Content-Type' => 'text/csv']);
    }

    public function render(FilterOptions $options)
    {
        [, $columns, $query] = $this->tabQuery();
        [$label, $table, $tabCols] = self::TABS[$this->tab] ?? self::TABS['rd'];
        $isModels = $this->tab === 'model';
        $isRetailers = $this->tab === 'rt';
        $columns = $isModels ? ['name'] : $tabCols; // table shows Type separately

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
            'isRetailers' => $isRetailers,
            'counts' => $counts,
            'rdOptions' => $isRetailers ? $options->distributors() : [],
            'rows' => $query->orderBy($columns[0])->paginate(30),
        ]);
    }
}
