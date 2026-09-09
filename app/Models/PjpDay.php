<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pjp_id', 'plan_date', 'day_status', 'notes'])]
class PjpDay extends Model
{
    protected function casts(): array
    {
        return ['plan_date' => 'date'];
    }

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }

    public function retailers(): HasMany
    {
        return $this->hasMany(PjpDayRetailer::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(PjpVisit::class);
    }

    public function statusLabel(): string
    {
        return config("pjp.day_statuses.{$this->day_status}", $this->day_status);
    }
}
