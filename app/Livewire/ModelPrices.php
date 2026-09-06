<?php

namespace App\Livewire;

use App\Models\ModelPrice;
use App\Services\Reporting\PriceService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Model Prices')]
class ModelPrices extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $editing = null;   // model name whose panel is open

    // add-price form
    public string $newPrice = '';

    public string $newFrom = '';

    public string $newNote = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);
        $this->newFrom = now()->toDateString();
    }

    public function open(string $model): void
    {
        $this->editing = $this->editing === $model ? null : $model;
        $this->reset('newPrice', 'newNote');
        $this->newFrom = now()->toDateString();
    }

    public function addPrice(PriceService $prices): void
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);
        $data = $this->validate([
            'newPrice' => 'required|numeric|min:0',
            'newFrom' => 'required|date',
            'newNote' => 'nullable|string|max:191',
        ]);

        $prices->set($this->editing, (float) $data['newPrice'], $data['newFrom'], $data['newNote'] ?: null, auth()->id());
        $this->reset('newPrice', 'newNote');
        session()->flash('status', "Price set for {$this->editing} from {$data['newFrom']}.");
    }

    public function deletePrice(int $id): void
    {
        abort_unless(auth()->user()?->can('masterdata.view'), 403);
        ModelPrice::whereKey($id)->delete();
    }

    public function render(PriceService $prices)
    {
        $models = DB::table('device_models')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', $this->search.'%'))
            ->orderBy('name')
            ->paginate(25);

        $current = $prices->currentMap();

        $counts = DB::table('model_prices')
            ->selectRaw('model, COUNT(*) c')->groupBy('model')->pluck('c', 'model');

        $history = $this->editing ? $prices->history($this->editing) : collect();

        return view('livewire.model-prices', [
            'models' => $models,
            'current' => $current,
            'counts' => $counts,
            'history' => $history,
            'symbol' => config('pricing.symbol'),
        ]);
    }
}
