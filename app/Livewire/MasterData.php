<?php

namespace App\Livewire;

use App\Models\DeviceModel;
use App\Models\RetailDistributor;
use App\Models\Retailer;
use App\Models\TerritoryOfficer;
use App\Models\User;
use App\Services\Reporting\FilterOptions;
use App\Support\ModelClassifier;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
        'rt' => ['Retailers', 'retailers', ['code', 'name', 'rd_code', 'area']],
        'model' => ['Models', 'device_models', ['name']],
        'tso' => ['TSOs', 'territory_officers', ['name']],
    ];

    /** Tabs that support manual add / edit / delete. */
    private const EDITABLE = ['rd', 'rt', 'model', 'tso'];

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formCode = '';

    public string $formName = '';

    public string $formRdCode = '';

    public string $formProductCode = '';

    public string $formStatus = 'out';

    /** Retailers tab: field attributes. */
    public string $formArea = '';

    public string $formAddress = '';

    public string $formPhone = '';

    public string $formLat = '';

    public string $formLng = '';

    /** Distributors tab: optionally create an RD-role login for the new distributor. */
    public bool $createLogin = false;

    public string $loginName = '';

    public string $loginEmail = '';

    public string $loginPassword = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);
    }

    /** Whether the current user may create an RD login alongside a distributor. */
    public function canCreateLogin(): bool
    {
        return (bool) auth()->user()?->can('users.manage');
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->modelStatus = '';
        $this->rdFilter = '';
        $this->cancelForm();
    }

    // ---- manual add / edit -------------------------------------------------

    private const FORM_FIELDS = [
        'showForm', 'editingId', 'formCode', 'formName', 'formRdCode', 'formProductCode', 'formStatus',
        'formArea', 'formAddress', 'formPhone', 'formLat', 'formLng',
        'createLogin', 'loginName', 'loginEmail', 'loginPassword',
    ];

    private function editable(): bool
    {
        return in_array($this->tab, self::EDITABLE, true);
    }

    public function newRow(): void
    {
        abort_unless(auth()->user()?->can('masterdata.view') && $this->editable(), 403);
        $this->reset(self::FORM_FIELDS);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function editRow(int $id): void
    {
        abort_unless(auth()->user()?->can('masterdata.view') && $this->editable(), 403);

        $row = DB::table(self::TABS[$this->tab][1])->find($id);
        abort_if(! $row, 404);

        $this->editingId = $id;
        $this->formCode = $row->code ?? '';
        $this->formName = $row->name ?? '';
        $this->formRdCode = $row->rd_code ?? '';
        $this->formProductCode = $row->product_code ?? '';
        $this->formStatus = $row->status ?? 'out';
        $this->formArea = $row->area ?? '';
        $this->formAddress = $row->address ?? '';
        $this->formPhone = $row->phone ?? '';
        $this->formLat = (string) ($row->latitude ?? '');
        $this->formLng = (string) ($row->longitude ?? '');
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->reset(self::FORM_FIELDS);
    }

    public function saveRow(): void
    {
        abort_unless(auth()->user()?->can('masterdata.view') && $this->editable(), 403);

        $table = self::TABS[$this->tab][1];

        if ($this->tab === 'tso') {
            $data = $this->validate([
                'formName' => ['required', 'string', 'max:120',
                    Rule::unique('territory_officers', 'name')->ignore($this->editingId)],
            ]);
            TerritoryOfficer::updateOrCreate(['id' => $this->editingId], ['name' => trim($data['formName'])]);
        } elseif ($this->tab === 'model') {
            // Model name is the natural key (raw records reference it by value), so
            // it is set on create and locked afterward.
            $rules = [
                'formProductCode' => ['nullable', 'string', 'max:60'],
                'formStatus' => ['required', 'in:running,out'],
            ];
            if (! $this->editingId) {
                $rules['formName'] = ['required', 'string', 'max:100', Rule::unique('device_models', 'name')];
            }
            $data = $this->validate($rules);

            $attrs = [
                'product_code' => trim((string) $data['formProductCode']) ?: null,
                'status' => $data['formStatus'],
            ];
            if (! $this->editingId) {
                $attrs['name'] = trim($data['formName']);
            }
            DeviceModel::updateOrCreate(['id' => $this->editingId], $attrs);
        } else {
            $rules = [
                'formName' => ['nullable', 'string', 'max:191'],
            ];
            // Code is immutable once set (raw records reference it by value).
            if (! $this->editingId) {
                $rules['formCode'] = ['required', 'string', 'max:40', Rule::unique($table, 'code')];
            }
            if ($this->tab === 'rt') {
                $rules['formRdCode'] = ['nullable', 'string', 'max:40'];
                $rules['formArea'] = ['nullable', 'string', 'max:120'];
                $rules['formAddress'] = ['nullable', 'string', 'max:255'];
                $rules['formPhone'] = ['nullable', 'string', 'max:30'];
                $rules['formLat'] = ['nullable', 'numeric', 'between:-90,90'];
                $rules['formLng'] = ['nullable', 'numeric', 'between:-180,180'];
            }
            $wantsLogin = $this->tab === 'rd' && ! $this->editingId && $this->createLogin && $this->canCreateLogin();
            if ($wantsLogin) {
                $rules['loginName'] = ['required', 'string', 'max:120'];
                $rules['loginEmail'] = ['required', 'email', Rule::unique('users', 'email')];
                $rules['loginPassword'] = ['required', 'string', 'min:8'];
            }

            $data = $this->validate($rules);

            $attrs = ['name' => trim((string) $data['formName']) ?: null];
            if (! $this->editingId) {
                $attrs['code'] = trim($data['formCode']);
            }
            if ($this->tab === 'rt') {
                $attrs['rd_code'] = trim((string) ($data['formRdCode'] ?? '')) ?: null;
                $attrs['area'] = trim((string) ($data['formArea'] ?? '')) ?: null;
                $attrs['address'] = trim((string) ($data['formAddress'] ?? '')) ?: null;
                $attrs['phone'] = trim((string) ($data['formPhone'] ?? '')) ?: null;
                $attrs['latitude'] = ($data['formLat'] ?? '') === '' ? null : (float) $data['formLat'];
                $attrs['longitude'] = ($data['formLng'] ?? '') === '' ? null : (float) $data['formLng'];
            }

            $model = $this->tab === 'rd' ? RetailDistributor::class : Retailer::class;
            $model::updateOrCreate(['id' => $this->editingId], $attrs);

            if ($wantsLogin) {
                $user = User::create([
                    'name' => trim($data['loginName']),
                    'email' => trim($data['loginEmail']),
                    'password' => Hash::make($data['loginPassword']),
                    'scoped_rd_codes' => [trim($data['formCode'])],
                ]);
                $user->syncRoles(['RD']);
            }
        }

        app(FilterOptions::class)->forget();
        $createdLogin = $this->tab === 'rd' && ! $this->editingId && $this->createLogin && $this->canCreateLogin();
        $this->cancelForm();
        $this->resetPage();
        session()->flash('status', $createdLogin
            ? 'Distributor saved and an RD login was created for it.'
            : 'Saved.');
    }

    public function deleteRow(int $id): void
    {
        abort_unless(auth()->user()?->can('masterdata.view') && $this->editable(), 403);

        DB::table(self::TABS[$this->tab][1])->where('id', $id)->delete();
        app(FilterOptions::class)->forget();
        session()->flash('status', 'Deleted. It will reappear if it is still present in imported data.');
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
            $columns = ['name', 'product_code', 'status'];
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
        $columns = $isModels ? ['name', 'product_code'] : $tabCols; // Models add a Type column too

        $counts = $isModels
            ? DB::table('device_models')->selectRaw("
                COUNT(*) total,
                SUM(status = 'running') running,
                SUM(status = 'out') `out`
              ")->first()
            : null;

        $rows = $query->orderBy($columns[0])->paginate(30);

        return view('livewire.master-data', [
            'tabs' => self::TABS,
            'columns' => $columns,
            'isModels' => $isModels,
            'isRetailers' => $isRetailers,
            'editable' => $this->editable(),
            'canCreateLogin' => $this->canCreateLogin(),
            'counts' => $counts,
            'rdOptions' => $isRetailers ? $options->distributors() : [],
            'rows' => $rows,
        ]);
    }
}
