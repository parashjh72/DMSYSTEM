<?php

namespace App\Console\Commands;

use App\Models\FieldSales\LocationPing;
use Illuminate\Console\Command;

class PruneLocationPingsCommand extends Command
{
    protected $signature = 'fs:prune-locations';

    protected $description = 'Delete field-staff location history older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) config('field_sales.tracking.retention_days'));
        $cutoff = now()->subDays($days);
        $deleted = 0;

        // Bounded batches keep each DELETE short on a large table.
        do {
            $ids = LocationPing::query()->where('recorded_at', '<', $cutoff)->orderBy('id')->limit(5000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : LocationPing::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 5000);

        $this->info("Deleted {$deleted} location ping(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
