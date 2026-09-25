<?php

namespace Tests\Feature\FieldSales;

use App\Livewire\Attendance;
use App\Models\FieldSales\TrackingConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendancePageTrackingTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $tso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->tso = $this->makeUser('TSO', ['RD001']);
    }

    public function test_attendance_page_asks_for_consent_and_shows_the_bs_date(): void
    {
        Livewire::actingAs($this->tso)->test(Attendance::class)
            ->assertSee('9 Aswin 2083 BS')
            ->assertSee('Share location during duty hours')
            ->call('acceptTrackingConsent')
            ->assertDontSee('Share location during duty hours')
            ->assertSee('Starts when you check in.');

        $this->assertSame(1, TrackingConsent::query()->where('user_id', $this->tso->id)->whereNull('revoked_at')->count());
    }

    public function test_tracker_runs_after_check_in_and_can_be_turned_off(): void
    {
        TrackingConsent::query()->create(['user_id' => $this->tso->id, 'consent_version' => 1, 'accepted_at' => now()]);
        $this->attendance($this->tso, '2026-09-25', '09:30');

        Livewire::actingAs($this->tso)->test(Attendance::class)
            ->assertSee('Every 5 min')
            ->call('revokeTrackingConsent')
            ->assertSee('Share location during duty hours');
    }
}
