<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'status'])]
class DeviceModel extends Model
{
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }
}
