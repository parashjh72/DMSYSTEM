<?php

namespace Tests\Feature\FieldSales;

use App\Livewire\FieldSales\Geofences;
use App\Livewire\FieldSales\Hierarchy;
use App\Livewire\FieldSales\Policies;
use App\Models\FieldSales\Area;
use App\Models\FieldSales\AttendancePolicy;
use App\Models\FieldSales\Geofence;
use App\Models\FieldSales\Holiday;
use App\Models\FieldSales\Region;
use App\Models\RetailDistributor;
use App\Models\User;
use App\Support\FieldSalesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SetupScreensTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->admin = $this->makeUser('Admin');
    }

    public function test_admin_builds_region_area_and_assigns_a_distributor(): void
    {
        $distributor = RetailDistributor::query()->create(['code' => 'RD001', 'name' => 'Everest Traders']);

        $component = Livewire::actingAs($this->admin)->test(Hierarchy::class)
            ->set('regionCode', 'east')->set('regionName', 'Eastern')->call('saveRegion')
            ->assertHasNoErrors();
        $region = Region::query()->sole();
        $this->assertSame('EAST', $region->code);

        $component->set('areaRegionId', $region->id)->set('areaCode', 'brt')->set('areaName', 'Biratnagar')->call('saveArea')
            ->assertHasNoErrors();
        $area = Area::query()->sole();

        $component->call('selectArea', $area->id)->set('addRdCode', 'RD001')->call('assignDistributor');
        $this->assertSame([$distributor->id], $area->distributors()->pluck('retail_distributors.id')->all());

        $component->call('deleteRegion', $region->id)->assertSet('error', 'Move or delete this region’s areas first.');
        $this->assertDatabaseHas('fs_regions', ['id' => $region->id]);
    }

    public function test_region_code_must_be_unique(): void
    {
        Region::factory()->create(['code' => 'EAST']);

        Livewire::actingAs($this->admin)->test(Hierarchy::class)
            ->set('regionCode', 'EAST')->set('regionName', 'Again')->call('saveRegion')
            ->assertHasErrors(['regionCode' => 'unique']);
    }

    public function test_admin_adds_a_distributor_check_in_point(): void
    {
        RetailDistributor::query()->create(['code' => 'RD001', 'name' => 'Everest Traders']);

        Livewire::actingAs($this->admin)->test(Geofences::class)
            ->set('rdCode', 'RD001')
            ->assertSet('name', 'Everest Traders')
            ->call('save')
            ->assertHasErrors(['latitude' => 'required'])
            ->call('setFormLatLng', 'geofence-form', 27.7172, 85.324)
            ->set('radius', 150)
            ->call('save')
            ->assertHasNoErrors();

        $point = Geofence::query()->with('distributor')->sole();
        $this->assertSame('RD001', $point->distributor->code);
        $this->assertSame(150, $point->radius_metres);
    }

    public function test_admin_saves_company_and_area_duty_rules(): void
    {
        $area = Area::factory()->create();

        Livewire::actingAs($this->admin)->test(Policies::class)
            ->set('dutyStart', '10:00')->set('weeklyOff', ['6', '0'])->set('geofenceMode', 'block')
            ->call('savePolicy')->assertHasNoErrors()
            ->set('scope', (string) $area->id)
            ->assertSet('dutyStart', '10:00')
            ->set('dutyStart', '09:00')->set('dutyEnd', '08:00')
            ->call('savePolicy')->assertHasErrors(['dutyEnd' => 'after'])
            ->set('dutyEnd', '17:00')
            ->call('savePolicy')->assertHasNoErrors();

        $company = AttendancePolicy::query()->whereNull('area_id')->sole();
        $this->assertSame('block', $company->geofence_mode);
        $this->assertSame([6, 0], $company->weeklyOffDays());
        $this->assertSame('09:00', AttendancePolicy::query()->where('area_id', $area->id)->sole()->dutyStartTime());
    }

    public function test_admin_manages_holidays_and_the_daily_summary(): void
    {
        Livewire::actingAs($this->admin)->test(Policies::class)
            ->set('holidayDate', '2026-10-21')->set('holidayName', 'Vijaya Dashami')->call('addHoliday')->assertHasNoErrors()
            ->set('holidayDate', '2026-10-21')->set('holidayName', 'Duplicate')->call('addHoliday')->assertHasErrors('holidayDate')
            ->set('summaryEnabled', true)->set('summaryTime', '18:30')->call('saveSummary');

        $this->assertSame(['Vijaya Dashami'], Holiday::query()->pluck('name')->all());
        $this->assertTrue(FieldSalesConfig::all()['daily_summary_enabled']);
        $this->assertSame('18:30', FieldSalesConfig::all()['daily_summary_time']);
    }

    public function test_asm_cannot_call_setup_actions(): void
    {
        $this->actingAs($this->makeUser('ASM', ['RD001']));

        Livewire::test(Policies::class)->assertForbidden();
    }
}
