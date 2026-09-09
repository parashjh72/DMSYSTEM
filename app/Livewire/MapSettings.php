<?php

namespace App\Livewire;

use App\Support\MapConfig;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Map settings')]
class MapSettings extends Component
{
    public string $apiKey = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('Super Admin'), 403);
        $this->apiKey = MapConfig::apiKey();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('Super Admin'), 403);
        $this->validate(['apiKey' => ['nullable', 'string', 'max:255']]);

        MapConfig::save($this->apiKey);
        session()->flash('status', 'Map settings saved.');
    }

    public function render()
    {
        return view('livewire.map-settings');
    }
}
