<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'uuid', 'tso_id', 'asm_id', 'nsm_id', 'year', 'month', 'status',
    'planned_days', 'planned_visits', 'revision_count',
    'submitted_at', 'asm_reviewed_at', 'asm_approved_at', 'forwarded_to_nsm_at',
    'nsm_reviewed_at', 'final_approved_at', 'final_approved_by', 'locked_at',
])]
class Pjp extends Model
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
            'year' => 'integer',
            'month' => 'integer',
            'submitted_at' => 'datetime',
            'asm_reviewed_at' => 'datetime',
            'asm_approved_at' => 'datetime',
            'forwarded_to_nsm_at' => 'datetime',
            'nsm_reviewed_at' => 'datetime',
            'final_approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function tso(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tso_id');
    }

    public function asm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asm_id');
    }

    public function nsm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nsm_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(PjpDay::class)->orderBy('plan_date');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(PjpVisit::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PjpEvent::class)->orderBy('id');
    }

    public function monthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->status === 'final_approved';
    }

    public function isEditableByTso(): bool
    {
        return in_array($this->status, config('pjp.editable_statuses'), true);
    }

    public function statusLabel(): string
    {
        return config("pjp.statuses.{$this->status}", $this->status);
    }
}
