<?php

use App\Jobs\HeartbeatJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Health heartbeats read by the dashboard's System status card.
Schedule::call(fn () => Cache::put('heartbeat:scheduler', now()->timestamp, now()->addDay()))
    ->everyMinute()->name('scheduler-heartbeat');
Schedule::job(new HeartbeatJob)->everyMinute()->name('queue-heartbeat');

// Nightly safety-net rebuild of the reporting tables (incremental refresh already
// runs after every import; this catches any drift).
Schedule::command('reports:rebuild-summaries')->dailyAt('01:30')->withoutOverlapping();

// Release import batches whose worker died mid-run.
Schedule::command('import:reap-stale')->everyFiveMinutes()->withoutOverlapping();

// Drop export files past their retention window.
Schedule::command('exports:prune')->dailyAt('02:00');
