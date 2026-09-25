<?php

namespace Tests\Feature\FieldSales;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\AttendancePolicy;
use App\Models\FieldSales\Geofence;
use App\Models\FieldSales\LocationPing;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GeofenceCheckInTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $tso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->tso = $this->makeUser('TSO', ['RD001']);
        Geofence::factory()->forDistributor('RD001')->at(27.7172, 85.3240, 200)->create(['name' => 'RD001 Godown']);
    }

    private function checkIn(float $lat, float $lng): void
    {
        app(AttendanceService::class)->checkIn($this->tso, ['latitude' => $lat, 'longitude' => $lng, 'accuracy' => 10.0]);
    }

    private function todayRow(): AttendanceDay
    {
        return AttendanceDay::query()->where('user_id', $this->tso->id)->whereDate('attendance_date', '2026-09-25')->sole();
    }

    public function test_check_in_inside_the_distributor_point_is_recorded_as_inside(): void
    {
        $this->checkIn(27.7173, 85.3241);

        $row = $this->todayRow();
        $this->assertSame('inside', $row->check_in_geofence);
        $this->assertSame('RD001 Godown', $row->checkInGeofence->name);
        $this->assertLessThan(200, $row->check_in_distance_metres);
    }

    public function test_flag_mode_allows_a_check_in_outside_every_point_but_marks_it(): void
    {
        $this->checkIn(27.7262, 85.3240); // ~1 km north

        $row = $this->todayRow();
        $this->assertSame('outside', $row->check_in_geofence);
        $this->assertEqualsWithDelta(1000, $row->check_in_distance_metres, 20);
        $this->assertDatabaseCount('tso_attendances', 1);
    }

    public function test_block_mode_refuses_a_check_in_outside_every_point(): void
    {
        AttendancePolicy::fromDefaults()->fill(['geofence_mode' => 'block'])->save();

        try {
            $this->checkIn(27.7262, 85.3240);
            $this->fail('Expected the check-in to be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('from RD001 Godown', $e->getMessage());
        }

        $this->assertDatabaseCount('tso_attendances', 0);
    }

    public function test_off_mode_skips_the_geofence_check(): void
    {
        AttendancePolicy::fromDefaults()->fill(['geofence_mode' => 'off', 'area_id' => null])->save();

        $this->checkIn(27.7262, 85.3240);

        $this->assertNull($this->todayRow()->check_in_geofence);
    }

    public function test_user_without_any_check_in_point_is_recorded_as_none(): void
    {
        $this->tso->update(['scoped_rd_codes' => ['RD999']]);

        $this->checkIn(27.7262, 85.3240);

        $this->assertSame('none', $this->todayRow()->check_in_geofence);
    }

    public function test_office_point_applies_only_to_its_own_area(): void
    {
        $myArea = Area::factory()->withDistributors('RD001')->create();
        $otherArea = Area::factory()->create();
        Geofence::factory()->at(27.7000, 85.3000)->create(['name' => 'My office', 'area_id' => $myArea->id]);
        Geofence::factory()->at(27.6000, 85.2000)->create(['name' => 'Other office', 'area_id' => $otherArea->id]);

        $this->checkIn(27.6001, 85.2001); // standing at the other area's office

        $row = $this->todayRow();
        $this->assertSame('outside', $row->check_in_geofence);
        $this->assertSame('My office', $row->checkInGeofence->name);
    }

    public function test_check_in_and_check_out_add_points_to_the_route(): void
    {
        $this->checkIn(27.7173, 85.3241);
        $this->travel(8)->hours();
        app(AttendanceService::class)->checkOut($this->tso, ['latitude' => 27.7174, 'longitude' => 85.3242, 'accuracy' => 10.0]);

        $this->assertSame(['check_in', 'check_out'], LocationPing::query()->orderBy('recorded_at')->pluck('source')->all());
        $this->assertSame('inside', $this->todayRow()->check_out_geofence);
    }
}
