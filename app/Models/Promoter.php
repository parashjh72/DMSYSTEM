<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name', 'phone', 'type', 'rt_code', 'rt_name', 'rd_code', 'monthly_target', 'active', 'note', 'created_by',
])]
class Promoter extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'monthly_target' => 'integer',
        ];
    }

    public function typeLabel(): string
    {
        return config("promoters.types.{$this->type}", $this->type);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
