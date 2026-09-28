<?php

namespace Tests\Feature;

use App\Livewire\DateConflicts;
use App\Livewire\DeviceEdit;
use App\Models\DeviceAudit;
use App\Models\SalesActivationRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeviceEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('Admin'));
        DB::table('retailers')->insert(['code' => 'NP065585', 'name' => 'New dipesh mobile gallery', 'rd_code' => 'MD001061', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('retail_distributors')->insert(['code' => 'MD001061', 'name' => 'New Rameshworam Suppliers', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_admin_edits_device_and_every_change_is_audited(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create(['tso' => 'Roshan Singh']);

        Livewire::actingAs($this->admin)
            ->test(DeviceEdit::class, ['imei' => $device->imei])
            ->set('tso', 'Madan Thapa')
            ->set('rd_code', 'MD001061')
            ->set('rt_code', 'NP065585')
            ->set('st_date', '2026-08-15')
            ->set('activation_date', '2026-09-02')
            ->set('reason', 'Invoice re-billed by RD')
            ->call('save')
            ->assertHasNoErrors();

        $device->refresh();
        $this->assertSame(['Madan Thapa', 'MD001061', 'New Rameshworam Suppliers', 'NP065585', 'New dipesh mobile gallery', '2026-08-15', '2026-09-02'],
            [$device->tso, $device->rd_code, $device->rd_name, $device->rt_code, $device->rt_name, $device->st_date->toDateString(), $device->activation_date->toDateString()]);

        $audits = DeviceAudit::where('imei', $device->imei)->orderBy('field')->get();
        $this->assertSame(['activation_date', 'rd_code', 'rt_code', 'st_date', 'tso'], $audits->pluck('field')->all());
        $this->assertTrue($audits->every(fn ($a) => $a->source === DeviceAudit::SOURCE_MANUAL
            && $a->user_id === $this->admin->id && $a->reason === 'Invoice re-billed by RD'));
        $this->assertSame([null, '2026-09-02'], [$audits[0]->old_value, $audits[0]->new_value]);
    }

    public function test_reason_is_required(): void
    {
        $device = SalesActivationRecord::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(DeviceEdit::class, ['imei' => $device->imei])
            ->set('tso', 'Madan Thapa')
            ->call('save')
            ->assertHasErrors(['reason' => 'required']);

        $this->assertSame(0, DeviceAudit::count());
    }

    public function test_edit_rejects_impossible_date_order(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();

        Livewire::actingAs($this->admin)
            ->test(DeviceEdit::class, ['imei' => $device->imei])
            ->set('activation_date', '2026-08-01')
            ->set('reason', 'Correcting activation')
            ->call('save')
            ->assertHasErrors('form');

        $this->assertNull($device->refresh()->activation_date);
    }

    public function test_unknown_rt_code_is_rejected(): void
    {
        $device = SalesActivationRecord::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(DeviceEdit::class, ['imei' => $device->imei])
            ->set('rt_code', 'NP999999')
            ->set('reason', 'Assigning retailer')
            ->call('save')
            ->assertHasErrors(['rt_code' => 'exists']);
    }

    public function test_non_admins_cannot_edit_or_see_conflicts(): void
    {
        $device = SalesActivationRecord::factory()->create();
        $tso = User::factory()->create();
        $tso->assignRole(Role::findOrCreate('TSO'));

        $this->actingAs($tso)->get(route('devices.edit', $device->imei))->assertForbidden();
        $this->actingAs($tso)->get(route('date-conflicts'))->assertForbidden();
    }

    public function test_date_conflicts_page_lists_existing_bad_rows(): void
    {
        $activatedEarly = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->activated('2026-08-05')->create();
        $soldBeforeSellIn = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-07-20')->create(['sell_in_date' => '2026-08-01']);
        $clean = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->activated('2026-08-20')->create();

        Livewire::actingAs($this->admin)
            ->test(DateConflicts::class)
            ->assertViewHas('counts', ['activation_before_st' => 1, 'st_before_sell_in' => 1])
            ->assertSee($activatedEarly->imei)
            ->assertSee($soldBeforeSellIn->imei)
            ->assertDontSee($clean->imei)
            ->set('rule', 'activation_before_st')
            ->assertDontSee($soldBeforeSellIn->imei);
    }
}
