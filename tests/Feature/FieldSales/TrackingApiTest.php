<?php

namespace Tests\Feature\FieldSales;

use App\Models\FieldSales\LocationPing;
use App\Models\FieldSales\TrackingConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackingApiTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $tso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->tso = $this->makeUser('TSO', ['RD001']);
    }

    private function consent(): void
    {
        TrackingConsent::query()->create([
            'user_id' => $this->tso->id,
            'consent_version' => config('field_sales.tracking.consent_version'),
            'accepted_at' => now(),
        ]);
    }

    /** A ping taken at the given Nepal time today. */
    private function ping(string $time, float $lat = 27.7172, float $lng = 85.3240, ?string $id = null): array
    {
        return [
            'id' => $id ?? (string) Str::uuid(),
            't' => Carbon::parse("2026-09-25 {$time}", self::TZ)->getTimestampMs(),
            'lat' => $lat,
            'lng' => $lng,
            'acc' => 12.0,
            'battery' => 80,
        ];
    }

    public function test_guest_gets_a_json_401(): void
    {
        $this->postJson(route('field-sales.api.locations'), ['pings' => [$this->ping('10:00')]])->assertUnauthorized();
    }

    public function test_only_field_officers_may_send_locations(): void
    {
        $this->actingAs($this->makeUser('ASM', ['RD001']))
            ->postJson(route('field-sales.api.locations'), ['pings' => [$this->ping('10:00')]])
            ->assertForbidden();
    }

    public function test_locations_are_refused_without_consent(): void
    {
        $this->attendance($this->tso, '2026-09-25', '09:30');

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [$this->ping('10:00')]])
            ->assertForbidden()
            ->assertJsonPath('code', 'consent_required');

        $this->assertDatabaseCount('fs_location_pings', 0);
    }

    public function test_invalid_pings_fail_validation(): void
    {
        $this->consent();

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [['id' => 'not-a-uuid', 't' => 1, 'lat' => 123, 'lng' => 85]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pings.0.id', 'pings.0.lat']);
    }

    public function test_on_duty_pings_are_stored_and_resends_are_counted_as_duplicates(): void
    {
        $this->consent();
        $this->attendance($this->tso, '2026-09-25', '09:30');
        $first = $this->ping('10:00');

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [$first, $this->ping('10:05', 27.7180)]])
            ->assertOk()
            ->assertExactJson(['accepted' => 2, 'duplicates' => 0, 'rejected' => []]);

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [$first]])
            ->assertOk()
            ->assertJson(['accepted' => 0, 'duplicates' => 1]);

        $ping = LocationPing::query()->where('client_uuid', $first['id'])->sole();
        $this->assertSame($this->tso->id, $ping->user_id);
        $this->assertSame('2026-09-25 04:15:00', $ping->recorded_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(80, $ping->battery);
        $this->assertFalse($ping->is_suspect);
    }

    public function test_fractional_millisecond_timestamps_are_accepted(): void
    {
        $this->consent();
        $this->attendance($this->tso, '2026-09-25', '09:30');
        $ping = ['t' => $this->ping('10:00')['t'] + 0.5] + $this->ping('10:00');

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [$ping]])
            ->assertOk()
            ->assertJsonPath('accepted', 1);
    }

    public function test_pings_outside_duty_hours_or_time_window_are_rejected(): void
    {
        $this->consent();
        $this->attendance($this->tso, '2026-09-25', '09:30', '11:00');
        $before = $this->ping('08:00');
        $after = $this->ping('11:30');
        $future = $this->ping('13:00');

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [$before, $this->ping('10:00'), $after, $future]])
            ->assertOk()
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath("rejected.{$before['id']}", 'off_duty')
            ->assertJsonPath("rejected.{$after['id']}", 'off_duty')
            ->assertJsonPath("rejected.{$future['id']}", 'future');
    }

    public function test_pings_on_a_day_without_check_in_are_rejected(): void
    {
        $this->consent();
        $ping = $this->ping('10:00');

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [$ping]])
            ->assertJsonPath("rejected.{$ping['id']}", 'off_duty');
    }

    public function test_impossible_jump_is_stored_as_suspect(): void
    {
        $this->consent();
        $this->attendance($this->tso, '2026-09-25', '09:30');
        $jump = $this->ping('10:01', 28.2096, 83.9856); // Kathmandu → Pokhara in one minute

        $this->actingAs($this->tso)
            ->postJson(route('field-sales.api.locations'), ['pings' => [$this->ping('10:00'), $jump]])
            ->assertJsonPath('accepted', 2);

        $this->assertTrue(LocationPing::query()->where('client_uuid', $jump['id'])->value('is_suspect'));
    }

    public function test_status_reports_tracking_only_between_check_in_and_check_out(): void
    {
        $this->actingAs($this->tso)->getJson(route('field-sales.api.status'))
            ->assertExactJson(['consented' => false, 'checked_in' => false, 'checked_out' => false, 'tracking' => false, 'interval_minutes' => 5]);

        $this->consent();
        $attendance = $this->attendance($this->tso, '2026-09-25', '09:30');
        $this->getJson(route('field-sales.api.status'))->assertJsonPath('tracking', true);

        $attendance->update(['check_out_at' => now(), 'status' => 'checked_out']);
        $this->getJson(route('field-sales.api.status'))->assertJsonPath('tracking', false);
    }

    public function test_consent_can_be_given_and_revoked(): void
    {
        $this->actingAs($this->tso)->postJson(route('field-sales.api.consent'))->assertOk()->assertJsonPath('consented', true);
        $this->assertNotNull(TrackingConsent::query()->where('user_id', $this->tso->id)->value('accepted_at'));

        $this->deleteJson(route('field-sales.api.consent'))->assertOk()->assertJsonPath('consented', false);
        $this->assertNotNull(TrackingConsent::query()->where('user_id', $this->tso->id)->value('revoked_at'));
    }

    public function test_new_consent_version_asks_again(): void
    {
        $this->consent();
        config(['field_sales.tracking.consent_version' => 2]);

        $this->actingAs($this->tso)->getJson(route('field-sales.api.status'))->assertJsonPath('consented', false);
    }
}
