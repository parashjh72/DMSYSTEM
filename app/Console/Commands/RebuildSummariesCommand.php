<?php

namespace App\Console\Commands;

use App\Services\Reporting\DashboardService;
use App\Services\Reporting\SummaryService;
use Illuminate\Console\Command;

class RebuildSummariesCommand extends Command
{
    protected $signature = 'reports:rebuild-summaries {--from=} {--to=}';

    protected $description = 'Rebuild the pre-aggregated reporting tables from sales_activation_records';

    public function handle(SummaryService $summaries, DashboardService $dashboard): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $this->info('Rebuilding summary tables'.($from || $to ? " ({$from} .. {$to})" : ' (full)').' ...');
        $start = microtime(true);

        $summaries->rebuildAll($from, $to);
        $dashboard->forget();

        $this->info(sprintf('Done in %.1fs.', microtime(true) - $start));

        return self::SUCCESS;
    }
}
