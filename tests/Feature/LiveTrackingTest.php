<?php

namespace Tests\Feature;

use App\Livewire\LiveTracking;
use App\Models\LocationPing;
use App\Models\PjpVisit;
use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LiveTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $tso;

    protected function setUp(): void
    {
        parent::setUp();
        // Mid-day local time, so "today" and "two hours ago" fall on the same attendance date.
        $this->travelTo(Carbon::parse('2026-06-15 13:00', config('attendance.timezone')));
        Role::findOrCreate('TSO');
        Role::findOrCreate('ASM');
        Role::findOrCreate('Admin');
        Permission::findOrCreate('attendance.view_all');
        Role::findByName('ASM')->givePermissionTo('attendance.view_all');
        Role::findByName('Admin')->givePermissionTo('attendance.view_all');

        $this->tso = User::factory()->create();
        $this->tso->assignRole('TSO');
    }

    private function checkIn(User $user, ?string $checkedOutAt = null): TsoAttendance
    {
        return TsoAttendance::create([
            'user_id' => $user->id,
            'attendance_date' => now(config('attendance.timezone'))->toDateString(),
            'check_in_at' => now()->subHours(2),
            'check_in_latitude' => 27.7172450,
            'check_in_longitude' => 85.3240450,
            'check_out_at' => $checkedOutAt,
            'status' => $checkedOutAt ? 'checked_out' : 'checked_in',
        ]);
    }

    /** @return array{lat: float, lng: float, accuracy: float, t: int} */
    private function ping(float $lat, float $lng, int $minutesAgo): array
    {
        return ['lat' => $lat, 'lng' => $lng, 'accuracy' => 10.0, 't' => now()->subMinutes($minutesAgo)->getTimestampMs()];
    }

    public function test_pings_are_stored_and_distance_ignores_jitter(): void
    {
        $attendance = $this->checkIn($this->tso);

        $this->actingAs($this->tso)->postJson(route('tracking.pings'), ['pings' => [
            $this->ping(27.7172460, 85.3240460, 90),   // ~0 m: jitter, not counted
            $this->ping(27.7262450, 85.3240450, 60),   // ~1 km north
            $this->ping(27.7262455, 85.3240455, 55),   // jitter
        ]])->assertOk()->assertJson(['tracking' => true, 'accepted' => 3]);

        $this->assertSame(3, LocationPing::where('tso_attendance_id', $attendance->id)->count());
        $km = LocationPing::where('tso_attendance_id', $attendance->id)->sum('segment_metres') / 1000;
        $this->assertEqualsWithDelta(1.0, $km, 0.02);
    }

    public function test_impossible_jumps_and_inaccurate_fixes_are_dropped(): void
    {
        $attendance = $this->checkIn($this->tso);

        $this->actingAs($this->tso)->postJson(route('tracking.pings'), ['pings' => [
            $this->ping(28.7172450, 85.3240450, 60),  // ~111 km an hour after check-in: plausible drive
            ['lat' => 27.72, 'lng' => 85.33, 'accuracy' => 900.0, 't' => now()->subMinutes(30)->getTimestampMs()],
            $this->ping(20.0, 85.3240450, 29),         // ~970 km a minute later: GPS glitch
        ]])->assertOk()->assertJson(['accepted' => 1]);

        $this->assertSame(1, LocationPing::where('tso_attendance_id', $attendance->id)->count());
    }

    public function test_offline_pings_sync_after_check_out_but_later_ones_are_dropped(): void
    {
        $attendance = $this->checkIn($this->tso, now()->subMinutes(10)->toDateTimeString());

        $this->actingAs($this->tso)->postJson(route('tracking.pings'), ['pings' => [
            $this->ping(27.7262450, 85.3240450, 40),  // taken on duty while offline
            $this->ping(27.7362450, 85.3240450, 1),   // after check-out
        ]])->assertOk()->assertJson(['tracking' => false, 'accepted' => 1]);

        $this->assertSame(1, LocationPing::where('tso_attendance_id', $attendance->id)->count());
    }

    public function test_pings_synced_next_day_are_filed_under_the_day_they_were_taken(): void
    {
        $yesterday = TsoAttendance::create([
            'user_id' => $this->tso->id,
            'attendance_date' => now(config('attendance.timezone'))->subDay()->toDateString(),
            'check_in_at' => now()->subDay()->subHours(3),
            'check_in_latitude' => 27.7172450,
            'check_in_longitude' => 85.3240450,
            'check_out_at' => now()->subDay(),
            'status' => 'checked_out',
        ]);
        $today = $this->checkIn($this->tso);

        $this->actingAs($this->tso)->postJson(route('tracking.pings'), ['pings' => [
            ['lat' => 27.7262450, 'lng' => 85.3240450, 'accuracy' => 10.0, 't' => now()->subDay()->subHour()->getTimestampMs()],
            $this->ping(27.7262450, 85.3340450, 30),
        ]])->assertOk()->assertJson(['tracking' => true, 'accepted' => 2]);

        $this->assertSame(1, LocationPing::where('tso_attendance_id', $yesterday->id)->count());
        $this->assertSame(1, LocationPing::where('tso_attendance_id', $today->id)->count());
    }

    public function test_a_batch_delivered_twice_is_stored_once(): void
    {
        $attendance = $this->checkIn($this->tso);
        $batch = ['pings' => [$this->ping(27.7262450, 85.3240450, 60), $this->ping(27.7362450, 85.3240450, 50)]];

        $this->actingAs($this->tso)->postJson(route('tracking.pings'), $batch)->assertJson(['accepted' => 2]);
        $this->actingAs($this->tso)->postJson(route('tracking.pings'), $batch)->assertJson(['accepted' => 0]);

        $this->assertSame(2, LocationPing::where('tso_attendance_id', $attendance->id)->count());
    }

    public function test_non_field_users_cannot_send_pings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('tracking.pings'), ['pings' => [$this->ping(27.72, 85.33, 1)]])
            ->assertForbidden();
    }

    public function test_path_view_shows_distance_and_visits(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $attendance = $this->checkIn($this->tso);
        LocationPing::create([
            'user_id' => $this->tso->id, 'tso_attendance_id' => $attendance->id,
            'recorded_at' => now()->subHour(), 'latitude' => 27.7262450, 'longitude' => 85.3240450, 'segment_metres' => 1000,
        ]);
        PjpVisit::create([
            'user_id' => $this->tso->id, 'rt_code' => 'RT-9001', 'visited_at' => now()->subMinutes(30),
            'latitude' => 27.7262450, 'longitude' => 85.3240450, 'note' => 'Stock check',
        ]);

        Livewire::actingAs($admin)
            ->test(LiveTracking::class)
            ->assertSee($this->tso->name)
            ->call('showUser', $this->tso->id)
            ->assertSee('1 km')
            ->assertSee('RT-9001')
            ->assertSee('Stock check');
    }

    public function test_asm_only_sees_own_team(): void
    {
        $asm = User::factory()->create();
        $asm->assignRole('ASM');
        $this->tso->update(['reports_to_id' => $asm->id]);

        $other = User::factory()->create();
        $other->assignRole('TSO');
        $this->checkIn($this->tso);
        $this->checkIn($other);

        Livewire::actingAs($asm)
            ->test(LiveTracking::class)
            ->assertSee($this->tso->name)
            ->assertDontSee($other->name);

        Livewire::actingAs($asm)
            ->test(LiveTracking::class)
            ->call('showUser', $other->id)
            ->assertForbidden();
    }

    public function test_tso_cannot_open_the_tracking_page(): void
    {
        $this->actingAs($this->tso)->get(route('tracking'))->assertForbidden();
    }
}
