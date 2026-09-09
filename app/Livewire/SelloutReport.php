<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Sellout Report')]
class SelloutReport extends StockReport
{
    public const TYPES = [
        'rd' => 'RD-wise sellout',
        'rt' => 'RT-wise sellout',
        'tso' => 'TSO-wise sellout',
        'asm' => 'ASM-wise sellout',
        'model' => 'Model-wise sellout',
    ];

    protected function mode(): string
    {
        return 'sellout';
    }
}
