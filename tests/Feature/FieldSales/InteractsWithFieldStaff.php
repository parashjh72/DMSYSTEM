<?php

namespace Tests\Feature\FieldSales;

use App\Models\TsoAttendance;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * Shared setup for Field Sales tests: roles, users in the TSO → ASM chain,
 * attendance rows, and a fixed "now" of Friday 25 Sep 2026, 12:00 Nepal time.
 */
trait InteractsWithFieldStaff
{
    protected const TZ = 'Asia/Kathmandu';

    protected function setUpFieldStaff(): void
    {
        config([
            'field_sales.timezone' => self::TZ,
            'attendance.timezone' => self::TZ,
            'attendance.reverse_geocode' => false,
            'attendance.anti_mock.enabled' => false,
        ]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-25 12:00', self::TZ));
    }

    /** @param  list<string>  $rdCodes */
    protected function makeUser(string $role, array $rdCodes = [], ?User $manager = null): User
    {
        $user = User::factory()->create([
            'scoped_rd_codes' => $rdCodes ?: null,
            'reports_to_id' => $manager?->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Attendance row with Nepal-time punch times ("09:40"). */
    protected function attendance(User $user, string $date, string $in, ?string $out = null, float $lat = 27.7172, float $lng = 85.3240): TsoAttendance
    {
        $checkIn = Carbon::parse("{$date} {$in}", self::TZ)->utc();
        $checkOut = $out ? Carbon::parse("{$date} {$out}", self::TZ)->utc() : null;

        return TsoAttendance::query()->create([
            'user_id' => $user->id,
            'attendance_date' => $date,
            'check_in_at' => $checkIn,
            'check_in_latitude' => $lat,
            'check_in_longitude' => $lng,
            'check_out_at' => $checkOut,
            'check_out_latitude' => $out ? $lat : null,
            'check_out_longitude' => $out ? $lng : null,
            'working_minutes' => $checkOut ? (int) $checkIn->diffInMinutes($checkOut) : null,
            'status' => $out ? 'checked_out' : 'checked_in',
        ]);
    }
}
