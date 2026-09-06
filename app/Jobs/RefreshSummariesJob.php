<?php

namespace App\Jobs;

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

    public function handle(SummaryService $summaries): void
    {
        if ($this->affectedBatchId !== null) {
            $summaries->rebuildForBatch($this->affectedBatchId);

            return;
        }

        $summaries->rebuildAll($this->from, $this->to);
    }
}
