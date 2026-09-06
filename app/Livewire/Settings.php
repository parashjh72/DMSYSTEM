<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('settings.manage'), 403);
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
