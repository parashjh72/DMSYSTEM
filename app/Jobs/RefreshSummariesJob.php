<?php

namespace App\Jobs;

use App\Services\Reporting\DashboardService;
use App\Services\Reporting\SummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshSummariesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public ?int $affectedBatchId = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    public function handle(SummaryService $summaries, DashboardService $dashboard): void
    {
        if ($this->affectedBatchId !== null) {
            $summaries->rebuildForBatch($this->affectedBatchId);
        } else {
            $summaries->rebuildAll($this->from, $this->to);
        }

        // Summary tables just changed — drop the cached dashboard snapshot.
        $dashboard->forget();
    }
}
