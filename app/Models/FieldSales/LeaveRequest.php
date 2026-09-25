<?php

namespace App\Models\FieldSales;

use App\Models\User;
use Database\Factories\FieldSales\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'from_date', 'to_date', 'leave_type', 'half_day', 'reason',
    'status', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    public const TYPES = ['casual' => 'Casual', 'sick' => 'Sick', 'other' => 'Other'];

    protected $table = 'fs_leave_requests';

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'half_day' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function days(): int
    {
        return (int) $this->from_date->diffInDays($this->to_date) + 1;
    }
}
