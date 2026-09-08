<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledReportJob;
use App\Models\ScheduledReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchScheduledReportsCommand extends Command
{
    protected $signature = 'reports:dispatch-scheduled {--force : Ignore the once-per-day guard (for testing)}';

    protected $description = 'Queue any scheduled reports that are due to be emailed now';

    public function handle(): int
    {
        $now = CarbonImmutable::now(config('reports.timezone'));

        $due = ScheduledReport::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (ScheduledReport $r) => $this->option('force') || $r->isDue($now));

        if ($due->isEmpty()) {
            $this->info('No scheduled reports due.');

            return self::SUCCESS;
        }

        foreach ($due as $report) {
            // Claim the day first so an overlapping run cannot double-send.
            $report->forceFill(['last_run_on' => $now->toDateString()])->save();

            SendScheduledReportJob::dispatch($report->id)->onQueue('exports');
            $this->info("Queued: {$report->name} (#{$report->id})");
        }

        return self::SUCCESS;
    }
}
