<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    protected $fillable = [
        'name', 'export_type', 'format', 'period', 'date_basis', 'filters',
        'frequency', 'day_of_week', 'day_of_month', 'time', 'recipients',
        'is_active', 'last_run_on', 'last_run_at', 'last_status', 'last_error', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'recipients' => 'array',
            'is_active' => 'boolean',
            'last_run_on' => 'date',
            'last_run_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Human summary of the schedule, e.g. "Every Monday at 07:00". */
    public function scheduleLabel(): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return match ($this->frequency) {
            'weekly' => 'Every '.($days[$this->day_of_week] ?? 'Monday').' at '.$this->time,
            'monthly' => 'Day '.($this->day_of_month ?? 1).' of each month at '.$this->time,
            default => 'Every day at '.$this->time,
        };
    }

    /**
     * Is this report due to run for the given "now" (already in the reports
     * timezone)? Also guards against a second send on the same day.
     */
    public function isDue(CarbonImmutable $now): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_run_on && $this->last_run_on->isSameDay($now)) {
            return false;
        }

        $matchesDay = match ($this->frequency) {
            'weekly' => (int) $now->dayOfWeek === (int) $this->day_of_week,
            'monthly' => (int) $now->day === (int) $this->day_of_month
                || ((int) $this->day_of_month > $now->daysInMonth && $now->day === $now->daysInMonth),
            default => true,
        };

        if (! $matchesDay) {
            return false;
        }

        return $now->format('H:i') >= $this->time;
    }
}
