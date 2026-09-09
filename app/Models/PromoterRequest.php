<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid', 'type', 'rt_code', 'rt_name', 'rd_code', 'promoter_name', 'proposed_target',
    'sales_snapshot', 'note', 'status', 'rejected_stage',
    'requested_by', 'asm_by', 'asm_at', 'asm_note', 'nsm_by', 'nsm_at', 'nsm_note', 'promoter_id',
])]
class PromoterRequest extends Model
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
            'sales_snapshot' => 'array',
            'proposed_target' => 'integer',
            'asm_at' => 'datetime',
            'nsm_at' => 'datetime',
        ];
    }

    public function typeLabel(): string
    {
        return config("promoters.types.{$this->type}", $this->type);
    }

    public function statusLabel(): string
    {
        return [
            'pending_asm' => 'Awaiting ASM',
            'pending_nsm' => 'Awaiting NSM',
            'approved' => 'Approved',
            'rejected' => 'Rejected'.($this->rejected_stage ? ' ('.strtoupper($this->rejected_stage).')' : ''),
        ][$this->status] ?? $this->status;
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function asmReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asm_by');
    }

    public function nsmReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nsm_by');
    }
}
