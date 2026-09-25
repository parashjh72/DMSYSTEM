<?php

namespace Tests\Feature\FieldSales;

use App\Livewire\FieldSales\AttendanceMonthly;
use App\Livewire\FieldSales\LiveMap;
use App\Livewire\FieldSales\RoutePlayback;
use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\LocationPing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagerScreensTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $asm;

    private User $myTso;

    private User $otherTso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->asm = $this->makeUser('ASM', ['RD001']);
        $this->myTso = $this->makeUser('TSO', ['RD001'], $this->asm);
        $this->otherTso = $this->makeUser('TSO', ['RD002'], $this->makeUser('ASM', ['RD002']));
    }

    /** @return array<string, array{string, string, int}> */
    public static function pageAccess(): array
    {
        return [
            'map: ASM' => ['field-sales.map', 'ASM', 200],
            'map: TSO' => ['field-sales.map', 'TSO', 403],
            'map: RD' => ['field-sales.map', 'RD', 403],
            'route: NSM' => ['field-sales.route', 'NSM', 200],
            'route: TSO' => ['field-sales.route', 'TSO', 403],
            'monthly: ASM' => ['field-sales.attendance', 'ASM', 200],
            'monthly: TSO' => ['field-sales.attendance', 'TSO', 403],
            'regions: Admin' => ['field-sales.setup.hierarchy', 'Admin', 200],
            'regions: ASM' => ['field-sales.setup.hierarchy', 'ASM', 403],
            'points: NSM' => ['field-sales.setup.geofences', 'NSM', 200],
            'points: ASM' => ['field-sales.setup.geofences', 'ASM', 403],
            'rules: Super Admin' => ['field-sales.setup.policies', 'Super Admin', 200],
            'rules: TSO' => ['field-sales.setup.policies', 'TSO', 403],
        ];
    }

    #[DataProvider('pageAccess')]
    public function test_page_access_follows_roles(string $route, string $role, int $status): void
    {
        $this->actingAs($this->makeUser($role, in_array($role, ['ASM', 'TSO', 'RD'], true) ? ['RD001'] : []))
            ->get(route($route))
            ->assertStatus($status);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('field-sales.map'))->assertRedirect(route('login'));
    }

    public function test_live_map_shows_only_the_managers_own_team(): void
    {
        $this->attendance($this->myTso, '2026-09-25', '09:30');
        LocationPing::query()->create([
            'user_id' => $this->myTso->id, 'client_uuid' => (string) Str::uuid(),
            'recorded_at' => Carbon::parse('2026-09-25 11:55', self::TZ)->utc(),
            'latitude' => 27.71, 'longitude' => 85.32, 'accuracy' => 10,
        ]);

        $staff = Livewire::actingAs($this->asm)->test(LiveMap::class)->get('staff');

        $this->assertSame([$this->myTso->id], array_column($staff, 'id'));
        $this->assertSame('active', $staff[0]['state']);
        $this->assertSame(5, $staff[0]['last']['minutes_ago']);
    }

    public function test_live_map_states_for_national_viewer(): void
    {
        $this->attendance($this->otherTso, '2026-09-25', '09:30');

        $staff = collect(Livewire::actingAs($this->makeUser('NSM'))->test(LiveMap::class)->get('staff'))->keyBy('id');

        $this->assertSame('not_checked_in', $staff[$this->myTso->id]['state']);
        $this->assertSame('offline', $staff[$this->otherTso->id]['state']);
    }

    public function test_route_playback_hides_officers_outside_the_team(): void
    {
        Livewire::actingAs($this->asm)->test(RoutePlayback::class, ['user' => $this->otherTso->id])
            ->assertSee('Choose a field officer to see their route.');

        Livewire::actingAs($this->asm)->test(RoutePlayback::class, ['user' => $this->myTso->id])
            ->assertDontSee('Choose a field officer to see their route.')
            ->assertSee('Distance');
    }

    public function test_monthly_report_shows_statuses_and_exports_csv(): void
    {
        $this->attendance($this->myTso, '2026-09-24', '10:00', '18:00');

        $component = Livewire::actingAs($this->asm)->test(AttendanceMonthly::class, ['month' => '2026-09'])
            ->call('recalculate')
            ->assertSee($this->myTso->name)
            ->assertDontSee($this->otherTso->name);

        $this->assertSame('late', AttendanceDay::query()->where('user_id', $this->myTso->id)->whereDate('attendance_date', '2026-09-24')->value('status'));

        $component->call('export')->assertFileDownloaded('field-attendance-2026-09.csv');
    }
}
