<?php

namespace App\Livewire;

use App\Services\Import\ImportDateGuard;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Devices already stored with an impossible date order (activation before ST,
 * or ST before sell-in), so the source data can be fixed. New imports hold such
 * rows back (see ImportDateGuard); this page covers what is already in the table.
 */
#[Layout('components.layouts.app')]
#[Title('Date Conflicts')]
class DateConflicts extends Component
{
    use WithPagination;

    /** all | activation_before_st | st_before_sell_in */
    #[Url]
    public string $rule = 'all';

    #[Url]
    public string $rdCode = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('devices.edit'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['rule', 'rdCode'], true)) {
            $this->resetPage();
        }
    }

    private function query(): Builder
    {
        $activationBeforeSt = fn (Builder $q) => $q->whereNotNull('activation_date')->whereNotNull('st_date')->whereColumn('activation_date', '<', 'st_date');
        $stBeforeSellIn = fn (Builder $q) => $q->whereNotNull('st_date')->whereNotNull('sell_in_date')->whereColumn('st_date', '<', 'sell_in_date');

        return DB::table('sales_activation_records')
            ->when($this->rdCode !== '', fn ($q) => $q->where('rd_code', $this->rdCode))
            ->where(fn (Builder $q) => match ($this->rule) {
                'activation_before_st' => $activationBeforeSt($q),
                'st_before_sell_in' => $stBeforeSellIn($q),
                default => $q->where($activationBeforeSt)->orWhere($stBeforeSellIn),
            });
    }

    public function download(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('devices.edit'), 403);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['IMEI', 'Model', 'RD Code', 'RD Name', 'RTCode', 'RT Name', 'Sell-in Date', 'ST Date', 'Activation', 'Problem']);
            $this->query()->orderBy('rd_code')->orderBy('imei')->chunk(2000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->imei, $r->model, $r->rd_code, $r->rd_name, $r->rt_code, $r->rt_name,
                        self::date($r->sell_in_date), self::date($r->st_date), self::date($r->activation_date),
                        ImportDateGuard::describe(self::date($r->st_date), self::date($r->activation_date), self::date($r->sell_in_date))]);
                }
            });
            fclose($out);
        }, 'date-conflicts-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public static function date(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }

    public function render()
    {
        $base = DB::table('sales_activation_records')->when($this->rdCode !== '', fn ($q) => $q->where('rd_code', $this->rdCode));

        return view('livewire.date-conflicts', [
            'rows' => $this->query()
                ->orderBy('rd_code')->orderBy('imei')
                ->paginate(50, ['imei', 'model', 'rd_code', 'rt_code', 'sell_in_date', 'st_date', 'activation_date']),
            'counts' => [
                'activation_before_st' => (clone $base)->whereNotNull('activation_date')->whereNotNull('st_date')->whereColumn('activation_date', '<', 'st_date')->count(),
                'st_before_sell_in' => (clone $base)->whereNotNull('st_date')->whereNotNull('sell_in_date')->whereColumn('st_date', '<', 'sell_in_date')->count(),
            ],
            'rdOptions' => DB::table('retail_distributors')->orderBy('code')->get(['code', 'name']),
        ]);
    }
}
