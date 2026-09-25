<?php

namespace Tests\Feature;

use App\Models\TsoAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class AttendanceAntiMockTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);
        \Spatie\Permission\Models\Role::findOrCreate('TSO');
        $this->user = User::factory()->create();
        $this->user->assignRole('TSO');
    }

    public function test_valid_gps_check_in_succeeds(): void
    {
        $attendance = $this->service->checkIn($this->user, [
            'latitude' => 27.717245,
            'longitude' => 85.324045,
            'accuracy' => 12.5,
        ]);

        $this->assertInstanceOf(TsoAttendance::class, $attendance);
        $this->assertEquals('checked_in', $attendance->status);
        $this->assertEquals(27.717245, (float) $attendance->check_in_latitude);
        $this->assertEquals(85.324045, (float) $attendance->check_in_longitude);
    }

    public function test_rejects_check_in_when_accuracy_is_zero_or_suspicious(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Suspicious GPS reading (0m accuracy)');

        $this->service->checkIn($this->user, [
            'latitude' => 27.717245,
            'longitude' => 85.324045,
            'accuracy' => 0.0,
        ]);
    }

    public function test_rejects_identical_repeated_mock_location_from_past_days(): void
    {
        // Yesterday's attendance recorded at specific coordinates (e.g. pinned in fake GPS)
        TsoAttendance::create([
            'user_id' => $this->user->id,
            'attendance_date' => Carbon::now(config('attendance.timezone'))->subDay()->toDateString(),
            'check_in_at' => now()->subDay(),
            'check_in_latitude' => 27.7172451,
            'check_in_longitude' => 85.3240452,
            'check_in_accuracy' => 10.0,
            'status' => 'checked_out',
        ]);

        // Today user uses the exact same mock location pin in Developer Options
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Developer Mock Location detected: Exact GPS coordinates match your previous attendance');

        $this->service->checkIn($this->user, [
            'latitude' => 27.7172451,
            'longitude' => 85.3240452,
            'accuracy' => 8.0,
        ]);
    }

    public function test_rejects_sub_meter_repeated_coordinates_from_past_days(): void
    {
        // Previous attendance 3 days ago
        TsoAttendance::create([
            'user_id' => $this->user->id,
            'attendance_date' => Carbon::now(config('attendance.timezone'))->subDays(3)->toDateString(),
            'check_in_at' => now()->subDays(3),
            'check_in_latitude' => 27.7172000,
            'check_in_longitude' => 85.3240000,
            'check_in_accuracy' => 10.0,
            'status' => 'checked_out',
        ]);

        // Difference of ~0.000004 deg lat (~0.44 metres)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Developer Mock Location detected');

        $this->service->checkIn($this->user, [
            'latitude' => 27.7172040,
            'longitude' => 85.3240000,
            'accuracy' => 9.0,
        ]);
    }

    public function test_allows_check_in_with_natural_gps_drift_from_previous_day(): void
    {
        // Yesterday
        TsoAttendance::create([
            'user_id' => $this->user->id,
            'attendance_date' => Carbon::now(config('attendance.timezone'))->subDay()->toDateString(),
            'check_in_at' => now()->subDay(),
            'check_in_latitude' => 27.7172000,
            'check_in_longitude' => 85.3240000,
            'check_in_accuracy' => 10.0,
            'status' => 'checked_out',
        ]);

        // Today natural satellite drift of ~10 metres (0.00009 degrees)
        $attendance = $this->service->checkIn($this->user, [
            'latitude' => 27.7172900,
            'longitude' => 85.3240000,
            'accuracy' => 11.0,
        ]);

        $this->assertInstanceOf(TsoAttendance::class, $attendance);
        $this->assertEquals('checked_in', $attendance->status);
    }

    public function test_rejects_identical_check_out_coordinates_after_shift(): void
    {
        // User checks in
        $record = TsoAttendance::create([
            'user_id' => $this->user->id,
            'attendance_date' => Carbon::now(config('attendance.timezone'))->toDateString(),
            'check_in_at' => now()->subHours(4),
            'check_in_latitude' => 27.7172450,
            'check_in_longitude' => 85.3240450,
            'check_in_accuracy' => 10.0,
            'status' => 'checked_in',
        ]);

        // User checks out 4 hours later with identical mock pin
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Developer Mock Location detected: Check-out coordinates are identical to check-in coordinates');

        $this->service->checkOut($this->user, [
            'latitude' => 27.7172450,
            'longitude' => 85.3240450,
            'accuracy' => 10.0,
        ]);
    }

    public function test_rejects_same_day_cross_user_collision_of_identical_pins(): void
    {
        $otherUser = User::factory()->create();

        // Other user checked in earlier today
        TsoAttendance::create([
            'user_id' => $otherUser->id,
            'attendance_date' => Carbon::now(config('attendance.timezone'))->toDateString(),
            'check_in_at' => now()->subHours(1),
            'check_in_latitude' => 27.7172450,
            'check_in_longitude' => 85.3240450,
            'check_in_accuracy' => 10.0,
            'status' => 'checked_in',
        ]);

        // Current user uses the same mock pin today
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Suspicious location: Identical GPS coordinates match another employee today');

        $this->service->checkIn($this->user, [
            'latitude' => 27.7172450,
            'longitude' => 85.3240450,
            'accuracy' => 10.0,
        ]);
    }

    public function test_distance_metres_calculation_is_accurate(): void
    {
        // 0.000008 degrees latitude is ~0.88 metres
        $dist = AttendanceService::distanceMetres(27.717200, 85.324000, 27.717208, 85.324000);
        $this->assertGreaterThan(0.8, $dist);
        $this->assertLessThan(1.0, $dist);

        // Same point is 0
        $this->assertEquals(0.0, AttendanceService::distanceMetres(27.7172, 85.3240, 27.7172, 85.3240));
    }

    public function test_livewire_component_displays_error_when_mock_location_detected(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('TSO');
        $this->user->assignRole('TSO');

        // Yesterday's attendance
        TsoAttendance::create([
            'user_id' => $this->user->id,
            'attendance_date' => Carbon::now(config('attendance.timezone'))->subDay()->toDateString(),
            'check_in_at' => now()->subDay(),
            'check_in_latitude' => 27.7172450,
            'check_in_longitude' => 85.3240450,
            'check_in_accuracy' => 10.0,
            'status' => 'checked_out',
        ]);

        \Livewire\Livewire::actingAs($this->user)
            ->test(\App\Livewire\Attendance::class)
            ->call('checkIn', 27.7172450, 85.3240450, 10.0, [
                'samples' => [
                    ['lat' => 27.7172450, 'lng' => 85.3240450, 'accuracy' => 10.0],
                    ['lat' => 27.7172460, 'lng' => 85.3240460, 'accuracy' => 10.0],
                    ['lat' => 27.7172470, 'lng' => 85.3240470, 'accuracy' => 10.0],
                ]
            ])
            ->assertSee('Developer Mock Location detected: Exact GPS coordinates match your previous attendance');
    }

    public function test_livewire_rejects_check_in_without_telemetry(): void
    {
        \Livewire\Livewire::actingAs($this->user)
            ->test(\App\Livewire\Attendance::class)
            ->call('checkIn', 27.7172450, 85.3240450, 10.0, null)
            ->assertSee('Live GPS satellite verification required');
    }

    public function test_rejects_zero_telemetry_jitter_across_samples(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Developer Mock Location detected: Zero GPS satellite jitter across consecutive fixes');

        $this->service->checkIn($this->user, [
            'latitude' => 27.7172450,
            'longitude' => 85.3240450,
            'accuracy' => 8.0,
            'telemetry' => [
                'samples' => [
                    ['lat' => 27.7172450, 'lng' => 85.3240450, 'accuracy' => 8.0],
                    ['lat' => 27.7172450, 'lng' => 85.3240450, 'accuracy' => 8.0],
                    ['lat' => 27.7172450, 'lng' => 85.3240450, 'accuracy' => 8.0],
                ]
            ]
        ]);
    }
}
