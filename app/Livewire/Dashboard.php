<?php

namespace App\Livewire;

use App\Services\Reporting\DashboardService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public int $seriesDays = 30;

    public function render(DashboardService $dashboard)
    {
        return view('livewire.dashboard', [
            'kpis' => $dashboard->kpis(),
            'series' => $dashboard->dailySeries($this->seriesDays),
            'topRd' => $dashboard->top('rd', 8),
            'topModel' => $dashboard->top('model', 8),
            'topTso' => $dashboard->top('tso', 8),
            'lag' => $dashboard->lagBuckets(),
            'health' => auth()->user()?->can('settings.manage') ? $dashboard->systemHealth() : null,
        ]);
    }
}
