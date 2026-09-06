<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['model', 'price', 'effective_from', 'note', 'created_by'])]
class ModelPrice extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
