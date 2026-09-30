<?php

namespace App\Livewire;

use App\Models\DeviceModel;
use App\Models\PjpDay;
use App\Models\PjpVisit;
use App\Services\PjpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

/**
 * The TSO's beat for today: the parties planned for the day, call status
 * (visited / planned), and recording each visit as effective (order taken,
 * with order lines) or non-effective (with the reason no order was placed).
 * Embedded on the Attendance page and the Beat Plan page.
 */
class TodayBeat extends Component
{
    public string $search = '';

    /** RT code whose visit form is open. */
    public ?string $visitRt = null;

    public string $outcome = PjpVisit::EFFECTIVE;

    /** @var list<array{model: string, qty: int|string}> */
    public array $lines = [];

    public string $reason = '';

    public string $note = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('pjp.create'), 403);
    }

    private function today(): string
    {
        return Carbon::now(config('pjp.timezone'))->toDateString();
    }

    private function day(): ?PjpDay
    {
        return PjpDay::query()
            ->with(['pjp', 'retailers', 'visits'])
            ->whereHas('pjp', fn ($q) => $q->where('tso_id', auth()->id()))
            ->whereDate('plan_date', $this->today())
            ->first();
    }

    public function openVisit(string $rtCode): void
    {
        $day = $this->day();
        abort_unless($day && $day->retailers->contains('rt_code', $rtCode), 404);

        $existing = $day->visits->firstWhere('rt_code', $rtCode);
        $this->resetErrorBag();
        $this->visitRt = $rtCode;
        $this->outcome = $existing?->outcome ?? PjpVisit::EFFECTIVE;
        $this->lines = collect($existing?->order_items ?? [])
            ->map(fn (array $l): array => ['model' => $l['model'], 'qty' => $l['qty']])
            ->values()->all() ?: [['model' => '', 'qty' => 1]];
        $this->reason = (string) ($existing?->no_order_reason ?? '');
        $this->note = (string) ($existing?->note ?? '');
    }

    public function closeVisit(): void
    {
        $this->reset('visitRt', 'outcome', 'lines', 'reason', 'note');
    }

    public function addLine(): void
    {
        $this->lines[] = ['model' => '', 'qty' => 1];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines) ?: [['model' => '', 'qty' => 1]];
    }

    /**
     * Called from Alpine with the browser's GPS fix (empty when unavailable).
     *
     * @param  array{latitude?: float, longitude?: float, accuracy?: float}  $gps
     */
    public function saveVisit(PjpService $service, array $gps = []): void
    {
        $day = $this->day();
        abort_unless($day && $this->visitRt !== null, 404);

        if ($day->pjp->status !== 'final_approved') {
            $this->addError('visit', 'Visits can be recorded once the beat plan has final approval.');

            return;
        }

        $this->validate([
            'outcome' => ['required', 'in:'.PjpVisit::EFFECTIVE.','.PjpVisit::NON_EFFECTIVE],
            'lines.*.qty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $gps = array_filter([
            'latitude' => is_numeric($gps['latitude'] ?? null) ? (float) $gps['latitude'] : null,
            'longitude' => is_numeric($gps['longitude'] ?? null) ? (float) $gps['longitude'] : null,
            'accuracy' => is_numeric($gps['accuracy'] ?? null) ? (float) $gps['accuracy'] : null,
        ], fn ($v) => $v !== null);

        try {
            $service->logVisit(auth()->user(), $day, $this->visitRt, $gps, $this->note, [
                'outcome' => $this->outcome,
                'items' => $this->outcome === PjpVisit::EFFECTIVE ? $this->lines : [],
                'reason' => $this->outcome === PjpVisit::NON_EFFECTIVE ? $this->reason : null,
            ]);
        } catch (RuntimeException $e) {
            $this->addError('visit', $e->getMessage());

            return;
        }

        session()->flash('beat-status', $this->outcome === PjpVisit::EFFECTIVE
            ? "Order recorded for {$this->visitRt}."
            : "No-order visit recorded for {$this->visitRt}.");
        $this->closeVisit();
    }

    public function render(PjpService $service)
    {
        $day = $this->day();
        $parties = collect();

        if ($day) {
            $codes = $day->retailers->pluck('rt_code');
            $info = DB::table('retailers')->whereIn('code', $codes)
                ->get(['code', 'name', 'area', 'address', 'phone', 'latitude', 'longitude'])->keyBy('code');
            $s = mb_strtolower(trim($this->search));

            $parties = $day->retailers
                ->map(function ($r) use ($info, $day): array {
                    $rt = $info->get($r->rt_code);

                    return [
                        'code' => $r->rt_code,
                        'name' => $rt->name ?? $r->rt_name,
                        'place' => collect([$rt->address ?? null, $rt->area ?? null])->filter()->implode(', '),
                        'phone' => $rt->phone ?? null,
                        'lat' => isset($rt->latitude) ? (float) $rt->latitude : null,
                        'lng' => isset($rt->longitude) ? (float) $rt->longitude : null,
                        'visit' => $day->visits->firstWhere('rt_code', $r->rt_code),
                    ];
                })
                ->when($s !== '', fn ($c) => $c->filter(fn (array $p): bool => str_contains(mb_strtolower($p['code'].' '.$p['name'].' '.$p['place']), $s)))
                ->values();
        }

        $modelOptions = $this->visitRt ? DeviceModel::running()->orderBy('name')->pluck('name')->all() : [];
        $prices = $this->visitRt ? $service->currentPrices(array_values(array_filter(array_column($this->lines, 'model')))) : [];

        return view('livewire.today-beat', [
            'day' => $day,
            'parties' => $parties,
            'visited' => $day?->visits->count() ?? 0,
            'planned' => $day?->retailers->count() ?? 0,
            'effective' => $day?->visits->where('outcome', PjpVisit::EFFECTIVE)->count() ?? 0,
            'orderValue' => (float) ($day?->visits->sum('order_value') ?? 0),
            'modelOptions' => $modelOptions,
            'prices' => $prices,
            'reasons' => config('pjp.no_order_reasons'),
            'canRecord' => $day?->pjp->status === 'final_approved',
        ]);
    }
}
