<?php

namespace Database\Seeders;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\AttendancePolicy;
use App\Models\FieldSales\Geofence;
use App\Models\FieldSales\Holiday;
use App\Models\FieldSales\LocationPing;
use App\Models\FieldSales\Region;
use App\Models\FieldSales\TrackingConsent;
use App\Models\RetailDistributor;
use App\Models\TsoAttendance;
use App\Models\User;
use App\Services\FieldSales\AttendanceDayBuilder;
use App\Services\FieldSales\RouteSummarizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sample Field Sales data for a STAGING copy: two regions, three areas with
 * demo distributors and check-in points, one ASM with three TSOs, a week of
 * attendance and today's GPS route. Refuses to run in production.
 *
 *   php artisan db:seed --class=FieldSalesDemoSeeder
 *
 * Demo logins use the password "password": demo.asm@dmsystem.local,
 * demo.tso1@dmsystem.local … demo.tso3@dmsystem.local.
 */
class FieldSalesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FieldSalesDemoSeeder is for staging only.');
        }

        $tz = config('field_sales.timezone');

        $bagmati = Region::query()->firstOrCreate(['code' => 'DEMO-BAG'], ['name' => 'Bagmati (demo)']);
        $gandaki = Region::query()->firstOrCreate(['code' => 'DEMO-GAN'], ['name' => 'Gandaki (demo)']);

        $areas = [
            ['DEMO-KTM', 'Kathmandu (demo)', $bagmati, 'DEMO-RD1', 27.7172, 85.3240],
            ['DEMO-LTP', 'Lalitpur (demo)', $bagmati, 'DEMO-RD2', 27.6644, 85.3188],
            ['DEMO-PKR', 'Pokhara (demo)', $gandaki, 'DEMO-RD3', 28.2096, 83.9856],
        ];

        foreach ($areas as [$code, $name, $region, $rdCode, $lat, $lng]) {
            $area = Area::query()->firstOrCreate(['code' => $code], ['name' => $name, 'region_id' => $region->id]);
            $distributor = RetailDistributor::query()->firstOrCreate(['code' => $rdCode], ['name' => $name.' Distributor']);
            $area->distributors()->syncWithoutDetaching([$distributor->id]);
            Geofence::query()->firstOrCreate(
                ['retail_distributor_id' => $distributor->id],
                ['name' => $distributor->name, 'latitude' => $lat, 'longitude' => $lng, 'radius_metres' => 200],
            );
        }

        if (AttendancePolicy::query()->whereNull('area_id')->doesntExist()) {
            AttendancePolicy::fromDefaults()->save();
        }
        Holiday::query()->firstOrCreate(
            ['holiday_date' => Carbon::now($tz)->addDays(10)->toDateString(), 'region_id' => null],
            ['name' => 'Demo holiday'],
        );

        $asm = $this->user('demo.asm@dmsystem.local', 'Demo ASM', 'ASM', ['DEMO-RD1', 'DEMO-RD2']);
        $tsos = [
            $this->user('demo.tso1@dmsystem.local', 'Demo TSO One', 'TSO', ['DEMO-RD1'], $asm),
            $this->user('demo.tso2@dmsystem.local', 'Demo TSO Two', 'TSO', ['DEMO-RD2'], $asm),
            $this->user('demo.tso3@dmsystem.local', 'Demo TSO Three', 'TSO', ['DEMO-RD1'], $asm),
        ];

        $today = Carbon::now($tz)->startOfDay();
        foreach ($tsos as $index => $tso) {
            TrackingConsent::query()->firstOrCreate(
                ['user_id' => $tso->id, 'consent_version' => config('field_sales.tracking.consent_version')],
                ['accepted_at' => now()],
            );

            for ($offset = 7; $offset >= 0; $offset--) {
                $day = $today->copy()->subDays($offset);
                if ($day->isSaturday() || ($index === 2 && $offset === 3)) {
                    continue; // weekly off, and one absence
                }
                $checkIn = $day->copy()->setTime(9, 20 + ($index * 12) + ($offset % 3) * 10);
                $checkOut = $offset === 0 ? null : $day->copy()->setTime($index === 1 && $offset === 2 ? 12 : 18, 5);
                if ($offset === 0 && $checkIn->isFuture()) {
                    continue;
                }

                TsoAttendance::query()->firstOrCreate(
                    ['user_id' => $tso->id, 'attendance_date' => $day->toDateString()],
                    [
                        'check_in_at' => $checkIn->copy()->utc(),
                        'check_in_latitude' => 27.7172 + $index * 0.001,
                        'check_in_longitude' => 85.3240,
                        'check_out_at' => $checkOut?->copy()->utc(),
                        'check_out_latitude' => $checkOut ? 27.7172 : null,
                        'check_out_longitude' => $checkOut ? 85.3240 : null,
                        'working_minutes' => $checkOut ? (int) $checkIn->diffInMinutes($checkOut) : null,
                        'status' => $checkOut ? 'checked_out' : 'checked_in',
                    ],
                );
            }

            $this->todayRoute($tso, $today, $index);
        }

        app(AttendanceDayBuilder::class)->build($tsos, $today->copy()->subDays(7), $today);
        foreach ($tsos as $tso) {
            app(RouteSummarizer::class)->summarise($tso, $today);
        }

        $this->command?->info('Field Sales demo data ready. Log in as demo.asm@dmsystem.local / password.');
    }

    /** @param  list<string>  $rdCodes */
    private function user(string $email, string $name, string $role, array $rdCodes, ?User $manager = null): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password'),
            'scoped_rd_codes' => $rdCodes,
            'reports_to_id' => $manager?->id,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    /** A pinged path from check-in until now, heading out from the distributor. */
    private function todayRoute(User $tso, Carbon $today, int $index): void
    {
        $attendance = TsoAttendance::query()->where('user_id', $tso->id)->whereDate('attendance_date', $today->toDateString())->first();
        if (! $attendance || LocationPing::query()->where('user_id', $tso->id)->where('recorded_at', '>=', $attendance->check_in_at)->exists()) {
            return;
        }

        $rows = [];
        $step = 0;
        for ($at = $attendance->check_in_at->copy(); $at->lte(now()); $at->addMinutes(5), $step++) {
            $rows[] = [
                'user_id' => $tso->id,
                'client_uuid' => (string) Str::uuid(),
                'recorded_at' => $at->copy(),
                'latitude' => 27.7172 + $index * 0.001 + $step * 0.0009,
                'longitude' => 85.3240 + sin($step / 4) * 0.004,
                'accuracy' => 12,
                'battery' => max(20, 95 - $step),
                'source' => 'tracker',
                'is_suspect' => false,
                'created_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            LocationPing::query()->insert($chunk);
        }
    }
}
