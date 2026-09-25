<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FieldSales\AttendanceDayBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BuildAttendanceDaysCommand extends Command
{
    protected $signature = 'fs:build-attendance-days
        {--from= : First date (Y-m-d), default yesterday}
        {--to= : Last date (Y-m-d), default today}';

    protected $description = 'Work out each field user\'s daily attendance status (present / late / half day / absent / leave / off)';

    public function handle(AttendanceDayBuilder $builder): int
    {
        $tz = config('field_sales.timezone');
        $from = $this->option('from') ? Carbon::parse($this->option('from'), $tz) : Carbon::now($tz)->subDay();
        $to = $this->option('to') ? Carbon::parse($this->option('to'), $tz) : Carbon::now($tz);

        $written = 0;
        User::query()->role('TSO')->chunkById(200, function ($users) use ($builder, $from, $to, &$written) {
            $written += $builder->build($users, $from, $to);
        });

        $this->info("Wrote {$written} attendance day(s) for {$from->toDateString()} → {$to->toDateString()}.");

        return self::SUCCESS;
    }
}
