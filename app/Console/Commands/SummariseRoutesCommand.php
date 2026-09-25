<?php

namespace App\Console\Commands;

use App\Models\FieldSales\LocationPing;
use App\Models\User;
use App\Services\FieldSales\RouteSummarizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SummariseRoutesCommand extends Command
{
    protected $signature = 'fs:summarise-routes {--date= : Date (Y-m-d) or "yesterday", default today}';

    protected $description = 'Rebuild the daily travel summary (km, idle time, route) for field users with location pings';

    public function handle(RouteSummarizer $summarizer): int
    {
        $tz = config('field_sales.timezone');
        $date = match ($this->option('date')) {
            null, '', 'today' => Carbon::now($tz),
            'yesterday' => Carbon::now($tz)->subDay(),
            default => Carbon::parse($this->option('date'), $tz),
        };
        $start = $date->copy()->startOfDay();

        $userIds = LocationPing::query()
            ->whereBetween('recorded_at', [$start->copy()->utc(), $start->copy()->endOfDay()->utc()])
            ->distinct()
            ->pluck('user_id');

        User::query()->whereIn('id', $userIds)->each(fn (User $user) => $summarizer->summarise($user, $date));

        $this->info("Summarised {$userIds->count()} route(s) for {$start->toDateString()}.");

        return self::SUCCESS;
    }
}
