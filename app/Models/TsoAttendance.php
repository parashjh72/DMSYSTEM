<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'attendance_date',
    'check_in_at', 'check_in_latitude', 'check_in_longitude', 'check_in_accuracy', 'check_in_address',
    'check_out_at', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy', 'check_out_address',
    'working_minutes', 'status',
])]
class TsoAttendance extends Model
{
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'working_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCheckedOut(): bool
    {
        return $this->status === 'checked_out';
    }

    public function workingLabel(): ?string
    {
        if ($this->working_minutes === null) {
            return null;
        }

        return intdiv($this->working_minutes, 60).'h '.($this->working_minutes % 60).'m';
    }
}
