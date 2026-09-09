<?php

namespace App\Livewire;

use App\Models\Promoter;
use App\Services\PromoterService;
use App\Services\Reporting\FilterOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

#[Layout('components.layouts.app')]
#[Title('Promoters (RA)')]
class Promoters extends Component
{
    #[Url]
    public string $month = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $rdFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fPhone = '';

    public string $fType = 'real_ra';

    public string $fRtCode = '';

    public string $fRtSearch = '';

    public int $fTarget = 0;

    public bool $fActive = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('promoters.manage'), 403);
        $this->month = $this->month ?: now(config('reports.timezone', 'Asia/Kathmandu'))->format('Y-m');
    }

    private const FORM = ['showForm', 'editingId', 'fName', 'fPhone', 'fType', 'fRtCode', 'fRtSearch', 'fTarget', 'fActive'];

    public function newRow(): void
    {
        $this->reset(self::FORM);
        $this->fType = 'real_ra';
        $this->fActive = true;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function editRow(int $id): void
    {
        $p = Promoter::findOrFail($id);
        $this->editingId = $p->id;
        $this->fName = $p->name;
        $this->fPhone = (string) $p->phone;
        $this->fType = $p->type;
        $this->fRtCode = $p->rt_code;
        $this->fRtSearch = trim($p->rt_code.' — '.$p->rt_name, ' —');
        $this->fTarget = $p->monthly_target;
        $this->fActive = $p->active;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->reset(self::FORM);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('promoters.manage'), 403);

        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fPhone' => ['nullable', 'string', 'max:30'],
            'fType' => ['required', Rule::in(array_keys(config('promoters.types')))],
            'fRtCode' => ['required', 'string', 'max:40'],
            'fTarget' => ['required', 'integer', 'min:0', 'max:100000'],
            'fActive' => ['boolean'],
        ]);

        $rt = DB::table('retailers')->where('code', $data['fRtCode'])->first();
        if (! $rt) {
            $this->addError('fRtCode', 'Pick a retailer from the list.');

            return;
        }

        Promoter::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => trim($data['fName']),
                'phone' => trim((string) $data['fPhone']) ?: null,
                'type' => $data['fType'],
                'rt_code' => $rt->code,
                'rt_name' => $rt->name,
                'rd_code' => $rt->rd_code,
                'monthly_target' => $data['fTarget'],
                'active' => $data['fActive'],
                ...($this->editingId ? [] : ['created_by' => auth()->id()]),
            ],
        );

        $this->cancelForm();
        session()->flash('status', 'Promoter saved.');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()?->can('promoters.manage'), 403);
        Promoter::whereKey($id)->delete();
        session()->flash('status', 'Promoter removed.');
    }

    public function pickRetailer(string $code): void
    {
        $rt = DB::table('retailers')->where('code', $code)->first();
        if ($rt) {
            $this->fRtCode = $rt->code;
            $this->fRtSearch = trim($rt->code.' — '.$rt->name, ' —');
        }
    }

    public function export(string $format, PromoterService $service)
    {
        abort_unless(auth()->user()?->can('promoters.manage'), 403);
        $data = $this->achievementData($service);

        $header = ['Promoter', 'Type', 'RT Code', 'RT Name', 'RD Code', 'Target', 'Achieved', 'Attainment %'];
        $rows = $data['rows']->map(fn ($r) => [
            $r['name'], $r['type_label'], $r['rt_code'], $r['rt_name'], $r['rd_code'],
            $r['target'], $r['achieved'], $r['pct'] ?? '',
        ])->all();

        $name = 'promoters-'.$data['month'];

        if ($format === 'xlsx') {
            $tmp = tempnam(sys_get_temp_dir(), 'promo').'.xlsx';
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(Row::fromValuesWithStyle($header, (new Style)
                ->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor(Color::DARK_BLUE)));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();

            return response()->download($tmp, "{$name}.xlsx")->deleteFileAfterSend();
        }

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, "{$name}.csv", ['Content-Type' => 'text/csv']);
    }

    private function achievementData(PromoterService $service): array
    {
        return $service->achievement(
            $this->month ?: null,
            $this->typeFilter ?: null,
            $this->rdFilter ?: null,
            activeOnly: false,
        );
    }

    public function render(PromoterService $service, FilterOptions $options)
    {
        $data = $this->achievementData($service);

        return view('livewire.promoters', [
            'month' => $data['month'],
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'types' => config('promoters.types'),
            'rdOptions' => $options->distributors(),
            'rtOptions' => $this->showForm ? $options->retailers(null, $this->fRtSearch)['options'] : [],
        ]);
    }
}
