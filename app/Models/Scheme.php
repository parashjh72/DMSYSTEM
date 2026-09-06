<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid', 'name', 'effective_from', 'effective_to', 'sellout_basis',
    'qualified_models', 'status', 'note', 'created_by',
])]
class Scheme extends Model
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function slabs(): HasMany
    {
        return $this->hasMany(SchemeSlab::class)->orderBy('slab_no');
    }

    public function basisColumn(): string
    {
        return $this->sellout_basis === 'st_date' ? 'st_date' : 'activation_date';
    }
}
