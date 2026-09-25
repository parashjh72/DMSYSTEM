<?php

namespace Tests\Feature\FieldSales;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\AttendancePolicy;
use App\Models\FieldSales\Holiday;
use App\Models\FieldSales\LeaveRequest;
use App\Models\User;
use App\Services\FieldSales\AttendanceDayBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Default rules: duty 09:30, 15 min grace, half day below 4 h, Saturday off.
 * "Today" is Friday 2026-09-25.
 */
class AttendanceDayBuilderTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $tso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->tso = $this->makeUser('TSO', ['RD001']);
    }

    private function build(string $from, string $to = '2026-09-25'): void
    {
        app(AttendanceDayBuilder::class)->build([$this->tso], Carbon::parse($from, self::TZ), Carbon::parse($to, self::TZ));
    }

    private function day(string $date): ?AttendanceDay
    {
        return AttendanceDay::query()->where('user_id', $this->tso->id)->whereDate('attendance_date', $date)->first();
    }

    public function test_check_in_within_the_grace_period_is_present(): void
    {
        $this->attendance($this->tso, '2026-09-24', '09:44', '18:00');
        $this->build('2026-09-24', '2026-09-24');

        $this->assertSame('present', $this->day('2026-09-24')->status);
        $this->assertSame(0, $this->day('2026-09-24')->late_minutes);
    }

    public function test_check_in_after_the_grace_period_is_late_with_minutes_from_duty_start(): void
    {
        $this->attendance($this->tso, '2026-09-24', '10:00', '18:00');
        $this->build('2026-09-24', '2026-09-24');

        $this->assertSame('late', $this->day('2026-09-24')->status);
        $this->assertSame(30, $this->day('2026-09-24')->late_minutes);
    }

    public function test_short_working_day_is_a_half_day(): void
    {
        $this->attendance($this->tso, '2026-09-24', '09:30', '12:00');
        $this->build('2026-09-24', '2026-09-24');

        $this->assertSame('half_day', $this->day('2026-09-24')->status);
    }

    public function test_past_day_without_check_out_is_flagged(): void
    {
        $this->attendance($this->tso, '2026-09-24', '09:30');
        $this->build('2026-09-24', '2026-09-24');

        $this->assertSame('present', $this->day('2026-09-24')->status);
        $this->assertTrue($this->day('2026-09-24')->missed_checkout);
    }

    public function test_missing_working_day_is_absent_and_saturday_is_weekly_off(): void
    {
        $this->build('2026-09-19', '2026-09-20');

        $this->assertSame('weekly_off', $this->day('2026-09-19')->status);
        $this->assertSame('absent', $this->day('2026-09-20')->status);
    }

    public function test_today_is_not_marked_absent_and_future_days_are_not_written(): void
    {
        $this->build('2026-09-25', '2026-09-30');

        $this->assertDatabaseCount('fs_attendance_days', 0);
    }

    public function test_approved_leave_marks_the_day_as_leave(): void
    {
        $leave = LeaveRequest::factory()->approved()->create([
            'user_id' => $this->tso->id, 'from_date' => '2026-09-23', 'to_date' => '2026-09-23',
        ]);
        LeaveRequest::factory()->create(['user_id' => $this->tso->id, 'from_date' => '2026-09-24', 'to_date' => '2026-09-24']);

        $this->build('2026-09-23', '2026-09-24');

        $this->assertSame('leave', $this->day('2026-09-23')->status);
        $this->assertSame($leave->id, $this->day('2026-09-23')->leave_request_id);
        $this->assertSame('absent', $this->day('2026-09-24')->status, 'Pending leave does not count.');
    }

    public function test_company_holiday_applies_but_another_regions_holiday_does_not(): void
    {
        $area = Area::factory()->withDistributors('RD001')->create();
        $otherArea = Area::factory()->create();
        Holiday::query()->create(['holiday_date' => '2026-09-22', 'name' => 'Company holiday']);
        Holiday::query()->create(['holiday_date' => '2026-09-23', 'name' => 'Own region', 'region_id' => $area->region_id]);
        Holiday::query()->create(['holiday_date' => '2026-09-24', 'name' => 'Other region', 'region_id' => $otherArea->region_id]);

        $this->build('2026-09-22', '2026-09-24');

        $this->assertSame('holiday', $this->day('2026-09-22')->status);
        $this->assertSame('holiday', $this->day('2026-09-23')->status);
        $this->assertSame('absent', $this->day('2026-09-24')->status);
    }

    public function test_area_policy_overrides_the_company_rules(): void
    {
        $area = Area::factory()->withDistributors('RD001')->create();
        AttendancePolicy::fromDefaults()->fill(['area_id' => $area->id, 'duty_start' => '10:00', 'weekly_off' => [0]])->save();
        $this->attendance($this->tso, '2026-09-24', '10:10', '18:00');

        $this->build('2026-09-19', '2026-09-24');

        $this->assertSame('present', $this->day('2026-09-24')->status);
        $this->assertSame('absent', $this->day('2026-09-19')->status, 'Saturday is a working day in this area.');
        $this->assertSame('weekly_off', $this->day('2026-09-20')->status);
    }

    public function test_rebuilding_updates_rows_without_losing_geofence_results(): void
    {
        $this->attendance($this->tso, '2026-09-24', '10:00', '18:00');
        AttendanceDay::query()->insert([
            'user_id' => $this->tso->id, 'attendance_date' => '2026-09-24',
            'check_in_geofence' => 'outside', 'check_in_distance_metres' => 900,
        ]);

        $this->build('2026-09-24', '2026-09-24');
        $this->build('2026-09-24', '2026-09-24');

        $this->assertSame(1, AttendanceDay::query()->count());
        $this->assertSame('late', $this->day('2026-09-24')->status);
        $this->assertSame('outside', $this->day('2026-09-24')->check_in_geofence);
    }

    public function test_command_builds_yesterday_and_today_for_every_field_user(): void
    {
        $this->artisan('fs:build-attendance-days')->assertSuccessful();

        $this->assertSame('absent', $this->day('2026-09-24')->status);
        $this->assertNull($this->day('2026-09-25'));
    }
}
