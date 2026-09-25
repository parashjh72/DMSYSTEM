<?php

namespace App\Console\Commands;

use App\Mail\FieldSales\DailyAttendanceSummaryMail;
use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\DailyRoute;
use App\Models\TsoAttendance;
use App\Models\User;
use App\Services\FieldSales\AttendanceDayBuilder;
use App\Services\FieldSales\RouteSummarizer;
use App\Support\FieldSalesConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyAttendanceSummaryCommand extends Command
{
    protected $signature = 'fs:send-daily-summary {--force : Send now, ignoring the enabled flag, send time and once-per-day guard}';

    protected $description = 'Email each ASM today\'s attendance and travel for their team';

    public function handle(AttendanceDayBuilder $days, RouteSummarizer $routes): int
    {
        $settings = FieldSalesConfig::all();
        $tz = config('field_sales.timezone');
        $now = Carbon::now($tz);
        $today = $now->toDateString();

        if (! $this->option('force')) {
            if (! $settings['daily_summary_enabled'] || $now->format('H:i') < $settings['daily_summary_time']
                || $settings['daily_summary_last_sent_on'] === $today) {
                return self::SUCCESS;
            }
            // Claim the day first so an overlapping run cannot double-send.
            FieldSalesConfig::save(['daily_summary_last_sent_on' => $today]);
        }

        $sent = 0;
        foreach (User::query()->role('ASM')->whereNotNull('email')->get() as $manager) {
            $team = $manager->subordinates()->role('TSO')->orderBy('name')->get();
            if ($team->isEmpty()) {
                continue;
            }

            $days->build($team, $now, $now);
            $team->each(fn (User $user) => $routes->summarise($user, $now));

            try {
                Mail::to($manager->email)->send(new DailyAttendanceSummaryMail($manager, $now->copy()->startOfDay(), ...$this->report($team, $today)));
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->info("Sent {$sent} daily summary email(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $team
     * @return array{0: list<array{name: string, status: string, check_in: ?string, check_out: ?string, km: float, geofence: ?string}>, 1: array<string, int>}
     */
    private function report($team, string $date): array
    {
        $tz = config('field_sales.timezone');
        $ids = $team->pluck('id');
        $statuses = AttendanceDay::query()->whereIn('user_id', $ids)->whereDate('attendance_date', $date)->get()->keyBy('user_id');
        $attendances = TsoAttendance::query()->whereIn('user_id', $ids)->whereDate('attendance_date', $date)->get()->keyBy('user_id');
        $km = DailyRoute::query()->whereIn('user_id', $ids)->whereDate('route_date', $date)->pluck('distance_km', 'user_id');

        $rows = $team->map(function (User $user) use ($statuses, $attendances, $km, $tz) {
            $day = $statuses->get($user->id);
            $attendance = $attendances->get($user->id);

            return [
                'name' => $user->name,
                'status' => $day?->status ? AttendanceDay::STATUSES[$day->status][1] : 'Not checked in',
                'check_in' => $attendance?->check_in_at?->setTimezone($tz)->format('H:i'),
                'check_out' => $attendance?->check_out_at?->setTimezone($tz)->format('H:i'),
                'km' => (float) ($km[$user->id] ?? 0),
                'geofence' => match ($day?->check_in_geofence) {
                    'inside' => 'Inside',
                    'outside' => 'Outside ('.number_format((int) $day->check_in_distance_metres).' m)',
                    default => null,
                },
            ];
        })->all();

        $totals = collect($rows)->countBy('status')->sortKeys()->all();

        return [$rows, $totals];
    }
}
