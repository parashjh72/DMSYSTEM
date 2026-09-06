<?php

namespace App\Livewire;

use App\Models\RecordTransfer;
use App\Services\Reporting\FilterOptions;
use App\Services\TransferService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Transfer records')]
class Transfer extends Component
{
    public string $mode = 'imei_list';   // imei_list | retailer

    // shared target
    public string $targetRt = '';

    public string $targetSearch = '';

    public bool $moveDistributor = true;

    // imei_list mode
    public string $imeis = '';

    // retailer mode
    public string $sourceRt = '';

    public string $sourceSearch = '';

    public bool $onlyInStock = false;

    /** last result: ['affected'=>int,'requested'=>int,'notFound'=>array,'uuid'=>string] */
    public ?array $result = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
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
        $this->validate(['targetRt' => 'required', 'imeis' => 'required|string']);

        $tokens = preg_split('/[\s,;]+/', trim($this->imeis), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $t = $service->byImeis($tokens, $this->targetArray(), auth()->id());

        $this->setResult($t);
        $this->reset('imeis');
    }

    public function transferRetailer(TransferService $service)
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
        $this->validate(['sourceRt' => 'required', 'targetRt' => 'required']);

        $t = $service->byRetailer($this->sourceRt, $this->targetArray(), $this->onlyInStock, auth()->id());

        $this->setResult($t);
        $this->reset('sourceRt', 'sourceSearch');
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
