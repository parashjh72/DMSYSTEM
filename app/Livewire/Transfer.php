<?php

namespace App\Livewire;

use App\Models\RecordTransfer;
use App\Services\Import\ImportService;
use App\Services\Import\SpreadsheetReader;
use App\Services\Reporting\FilterOptions;
use App\Services\TransferService;
use App\Support\Imei;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Transfer records')]
class Transfer extends Component
{
    use WithFileUploads;

    public string $mode = 'imei_list';   // imei_list | retailer

    // shared target
    public string $targetRt = '';

    public string $targetSearch = '';

    public bool $moveDistributor = true;

    /** Effective date of the move (Y-m-d). The device's original ST date is kept. */
    public string $transferDate = '';

    // imei_list mode
    public string $imeis = '';

    /** Optional xlsx / csv with an IMEI column (or IMEIs in the first column). */
    public $imeiFile = null;

    // retailer mode
    public string $sourceRt = '';

    public string $sourceSearch = '';

    public bool $onlyInStock = false;

    /** last result: ['affected'=>int,'requested'=>int,'notFound'=>array,'uuid'=>string] */
    public ?array $result = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        $this->transferDate = now()->toDateString();
    }

    public function updatedMode(): void
    {
        $this->reset('result');
    }

    private function targetArray(): array
    {
        $rt = DB::table('retailers')->where('code', $this->targetRt)->first();
        abort_if(! $rt, 422, 'Unknown target retailer.');

        $rdName = $rt->rd_code
            ? DB::table('retail_distributors')->where('code', $rt->rd_code)->value('name')
            : null;

        return [
            'rt_code' => $rt->code,
            'rt_name' => $rt->name,
            'rd_code' => $this->moveDistributor ? $rt->rd_code : null,
            'rd_name' => $this->moveDistributor ? $rdName : null,
        ];
    }

    public function transferImeis(TransferService $service)
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        $this->validate([
            'targetRt' => 'required',
            'transferDate' => 'required|date_format:Y-m-d|before_or_equal:today',
            'imeis' => 'required_without:imeiFile|nullable|string',
            'imeiFile' => 'nullable|file|max:20480|extensions:csv,txt,xlsx',
        ], ['imeis.required_without' => 'Paste IMEIs or upload an xlsx / csv file.']);

        $tokens = preg_split('/[\s,;]+/', trim($this->imeis), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($this->imeiFile) {
            array_push($tokens, ...$this->imeisFromFile());
        }

        try {
            $t = $service->byImeis($tokens, $this->targetArray(), auth()->id(), $this->transferDate);
        } catch (RuntimeException $e) {
            $this->addError('imeis', $e->getMessage());

            return;
        }

        $this->setResult($t);
        $this->reset('imeis', 'imeiFile');
    }

    public function transferRetailer(TransferService $service)
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        $this->validate([
            'sourceRt' => 'required',
            'targetRt' => 'required',
            'transferDate' => 'required|date_format:Y-m-d|before_or_equal:today',
        ]);

        $t = $service->byRetailer($this->sourceRt, $this->targetArray(), $this->onlyInStock, auth()->id(), $this->transferDate);

        $this->setResult($t);
        $this->reset('sourceRt', 'sourceSearch');
    }

    /**
     * IMEIs from the uploaded sheet: the column headed like "IMEI", else the first
     * column. A header cell that is itself an IMEI means the file has no header row.
     *
     * @return list<string>
     */
    private function imeisFromFile(): array
    {
        $type = app(ImportService::class)->detectType($this->imeiFile->getClientOriginalName(), $this->imeiFile->getMimeType());
        $reader = new SpreadsheetReader($this->imeiFile->getRealPath(), $type);

        $headers = $reader->headers();
        $aliases = config('import.header_aliases.imei', ['imei']);
        $column = 0;
        foreach ($headers as $index => $header) {
            if (in_array(strtolower(trim((string) $header)), $aliases, true)) {
                $column = $index;
                break;
            }
        }

        $imeis = [];
        if (Imei::isValid(Imei::clean($headers[$column] ?? ''))) {
            $imeis[] = (string) $headers[$column];
        }
        foreach ($reader->dataRows() as $cells) {
            $value = trim((string) ($cells[$column] ?? ''));
            if ($value !== '') {
                $imeis[] = $value;
            }
        }

        return $imeis;
    }

    private function setResult(RecordTransfer $t): void
    {
        $this->result = [
            'affected' => $t->affected_count,
            'requested' => $t->requested_count,
            'notFound' => $t->not_found ?? [],
            'uuid' => $t->uuid,
        ];
        session()->flash('status', "{$t->affected_count} record(s) transferred to {$t->to_rt_code}.");
    }

    public function render(FilterOptions $options)
    {
        return view('livewire.transfer', [
            'targetOptions' => $options->retailers(null, $this->targetSearch)['options'],
            'sourceOptions' => $options->retailers(null, $this->sourceSearch)['options'],
            'recent' => RecordTransfer::with('performer')->latest('id')->limit(15)->get(),
        ]);
    }
}
