<?php

namespace App\Livewire;

use App\Services\Reporting\FilterOptions;
use App\Services\Reporting\WodCoverageService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('WOD Coverage')]
class WodCoverage extends Component
{
    use WithPagination;

    /** rd | tso */
    #[Url]
    public string $type = 'rd';

    #[Url]
    public ?string $rdCode = null;

    #[Url]
    public ?string $model = null;

    /** '' = both, 'running', 'out' — filters by device_models.status. */
    #[Url]
    public string $lifecycle = '';

    public const TYPES = [
        'rd' => 'RD-wise',
        'tso' => 'TSO-wise',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'rdCode', 'model', 'lifecycle'], true)) {
            $this->resetPage();
        }
    }

    public function export(WodCoverageService $wod, FilterOptions $options): StreamedResponse
    {
        abort_unless(auth()->user()?->can('exports.view'), 403);

        $wod->forLifecycle($this->lifecycle ?: null);
        $columns = $wod->modelColumns($this->rdCode ?: null, $this->model ?: null);
        $rows = $wod->exportRows($this->type, $this->rdCode ?: null, $this->model ?: null, $columns['models'], $columns['hasOther']);

        $label = $this->type === 'tso' ? 'TSO' : 'RD';
        $header = array_merge(
            $this->type === 'tso' ? ['TSO'] : ['RD Code', 'RD Name'],
            $columns['models'],
            $columns['hasOther'] ? ['Other'] : [],
            ['Total retailers'],
        );

        return response()->streamDownload(function () use ($rows, $header, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($out, array_merge(
                    $r['labels'],
                    array_map(fn ($m) => $r['cells'][$m] ?? 0, $columns['models']),
                    $columns['hasOther'] ? [$r['other']] : [],
                    [$r['total']],
                ), ',', '"', '');
            }
            fclose($out);
        }, 'wod-coverage-'.strtolower($label).'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(WodCoverageService $wod, FilterOptions $options)
    {
        $rd = $this->rdCode ?: null;
        $model = $this->model ?: null;

        $wod->forLifecycle($this->lifecycle ?: null);
        $columns = $wod->modelColumns($rd, $model);

        $rows = $this->type === 'tso'
            ? $wod->tsoWise($rd, $model, $columns['models'], $columns['hasOther'], 50)
            : $wod->rdWise($rd, $model, $columns['models'], $columns['hasOther'], 50);

        return view('livewire.wod-coverage', [
            'rows' => $rows,
            'columns' => $columns,
            'summary' => $wod->summary($rd, $model),
            'types' => static::TYPES,
            'rdOptions' => $options->distributors(),
            'modelOptions' => $options->models(),
            'labelHeaders' => $this->type === 'tso' ? ['TSO'] : ['RD Code', 'RD Name'],
        ]);
    }
}
