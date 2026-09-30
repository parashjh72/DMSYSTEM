<?php

namespace Tests\Feature;

use App\Livewire\BeatVisitReport;
use App\Livewire\TodayBeat;
use App\Models\DeviceModel;
use App\Models\ModelPrice;
use App\Models\Pjp;
use App\Models\PjpDay;
use App\Models\PjpVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BeatPlanVisitTest extends TestCase
{
    use RefreshDatabase;

    protected User $tso;

    protected User $asm;

    protected PjpDay $day;

    protected function setUp(): void
    {
        parent::setUp();
        // Mid-day local time, so "today" and "two hours ago" fall on the same attendance date.
        $this->travelTo(Carbon::parse('2026-06-15 13:00', config('attendance.timezone')));
        foreach (['pjp.create', 'pjp.report', 'exports.view'] as $perm) {
            Permission::findOrCreate($perm);
        }
        Role::findOrCreate('TSO')->givePermissionTo('pjp.create');
        Role::findOrCreate('ASM')->givePermissionTo('pjp.report');

        $this->asm = User::factory()->create();
        $this->asm->assignRole('ASM');
        $this->tso = User::factory()->create(['reports_to_id' => $this->asm->id]);
        $this->tso->assignRole('TSO');

        $this->day = $this->beatDay($this->tso, 'final_approved', ['RT-1', 'RT-2']);

        DB::table('retailers')->insert([
            ['code' => 'RT-1', 'name' => 'Holy Palace', 'area' => 'Dhangadhi', 'phone' => '9800000001', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'RT-2', 'name' => 'Falex Foods', 'area' => 'Patel Nagar', 'phone' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DeviceModel::create(['name' => 'Phone X', 'status' => 'running']);
        ModelPrice::create(['model' => 'Phone X', 'price' => 15000, 'effective_from' => now()->subMonth()->toDateString()]);
    }

    /** @param  list<string>  $codes */
    private function beatDay(User $tso, string $status, array $codes): PjpDay
    {
        $today = Carbon::now(config('pjp.timezone'));
        $pjp = Pjp::create(['tso_id' => $tso->id, 'asm_id' => $tso->reports_to_id, 'year' => $today->year, 'month' => $today->month, 'status' => $status]);
        $day = PjpDay::create(['pjp_id' => $pjp->id, 'plan_date' => $today->toDateString(), 'day_status' => 'planned']);
        foreach ($codes as $code) {
            $day->retailers()->create(['rt_code' => $code, 'rt_name' => $code]);
        }

        return $day;
    }

    public function test_effective_visit_records_priced_order_lines(): void
    {
        Livewire::actingAs($this->tso)
            ->test(TodayBeat::class)
            ->assertSee('Holy Palace')
            ->assertSee('0/2')
            ->call('openVisit', 'RT-1')
            ->set('outcome', 'effective')
            ->set('lines', [['model' => 'Phone X', 'qty' => 2], ['model' => 'Phone X', 'qty' => 1]])
            ->call('saveVisit', ['latitude' => 27.7, 'longitude' => 85.3, 'accuracy' => 8])
            ->assertHasNoErrors()
            ->assertSee('1/2');

        $visit = PjpVisit::sole();
        $this->assertSame('effective', $visit->outcome);
        $this->assertSame([['model' => 'Phone X', 'qty' => 3, 'price' => 15000, 'amount' => 45000]], $visit->order_items);
        $this->assertEquals(45000, $visit->order_value);
    }

    public function test_non_effective_visit_requires_a_reason(): void
    {
        Livewire::actingAs($this->tso)
            ->test(TodayBeat::class)
            ->call('openVisit', 'RT-2')
            ->set('outcome', 'non_effective')
            ->call('saveVisit', [])
            ->assertHasErrors('visit')
            ->set('reason', 'stock_sufficient')
            ->call('saveVisit', [])
            ->assertHasNoErrors();

        $this->assertSame('stock_sufficient', PjpVisit::sole()->no_order_reason);
    }

    public function test_effective_visit_needs_at_least_one_product(): void
    {
        Livewire::actingAs($this->tso)
            ->test(TodayBeat::class)
            ->call('openVisit', 'RT-1')
            ->call('saveVisit', [])
            ->assertHasErrors('visit');

        $this->assertDatabaseCount('pjp_visits', 0);
    }

    public function test_visits_wait_for_final_approval(): void
    {
        $this->day->pjp->update(['status' => 'submitted']);

        Livewire::actingAs($this->tso)
            ->test(TodayBeat::class)
            ->assertSee('visits open after final approval')
            ->call('openVisit', 'RT-2')
            ->set('outcome', 'non_effective')
            ->set('reason', 'shop_closed')
            ->call('saveVisit', [])
            ->assertHasErrors('visit');
    }

    public function test_party_not_on_todays_beat_cannot_be_opened(): void
    {
        Livewire::actingAs($this->tso)
            ->test(TodayBeat::class)
            ->call('openVisit', 'RT-999')
            ->assertNotFound();
    }

    public function test_report_shows_visits_frequency_and_reasons_for_the_asm_team_only(): void
    {
        PjpVisit::create(['pjp_day_id' => $this->day->id, 'pjp_id' => $this->day->pjp_id, 'user_id' => $this->tso->id, 'rt_code' => 'RT-1',
            'visited_at' => now(), 'outcome' => 'effective', 'order_items' => [['model' => 'Phone X', 'qty' => 1, 'price' => 15000, 'amount' => 15000]], 'order_value' => 15000]);
        PjpVisit::create(['pjp_day_id' => $this->day->id, 'pjp_id' => $this->day->pjp_id, 'user_id' => $this->tso->id, 'rt_code' => 'RT-2',
            'visited_at' => now(), 'outcome' => 'non_effective', 'no_order_reason' => 'payment_pending']);

        $outsider = User::factory()->create();
        $outsider->assignRole('TSO');
        $otherDay = $this->beatDay($outsider, 'final_approved', ['RT-OUT']);
        PjpVisit::create(['pjp_day_id' => $otherDay->id, 'pjp_id' => $otherDay->pjp_id, 'user_id' => $outsider->id, 'rt_code' => 'RT-OUT', 'visited_at' => now()]);

        Livewire::actingAs($this->asm)
            ->test(BeatVisitReport::class)
            ->assertSee('Holy Palace')
            ->assertSee('Phone X ×1')
            ->assertSee('Payment / credit pending')
            ->assertDontSee('RT-OUT')
            ->set('view', 'frequency')
            ->assertSee('Falex Foods')
            ->set('view', 'reasons')
            ->assertSee('Payment / credit pending')
            ->assertSee('50%');
    }
}
