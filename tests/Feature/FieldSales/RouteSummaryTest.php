<?php

namespace Tests\Feature\FieldSales;

use App\Models\FieldSales\DailyRoute;
use App\Models\FieldSales\LocationPing;
use App\Models\User;
use App\Services\FieldSales\RouteSummarizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouteSummaryTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $tso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        $this->tso = $this->makeUser('TSO', ['RD001']);
    }

    private function ping(string $at, float $lat, float $accuracy = 10.0, bool $suspect = false, ?User $user = null): void
    {
        LocationPing::query()->create([
            'user_id' => ($user ?? $this->tso)->id,
            'client_uuid' => (string) Str::uuid(),
            'recorded_at' => Carbon::parse($at, self::TZ)->utc(),
            'latitude' => $lat,
            'longitude' => 85.3000,
            'accuracy' => $accuracy,
            'is_suspect' => $suspect,
        ]);
    }

    public function test_summary_counts_real_movement_and_idle_time_only(): void
    {
        $this->ping('2026-09-25 10:00', 27.70000);
        $this->ping('2026-09-25 10:05', 27.70004);          // ~4 m jitter
        $this->ping('2026-09-25 10:20', 27.70002);          // still parked: 20 min idle
        $this->ping('2026-09-25 10:25', 27.70100);          // +111 m
        $this->ping('2026-09-25 10:30', 27.75000, 500.0);   // inaccurate fix, ignored
        $this->ping('2026-09-25 10:33', 28.20000, 10.0, true); // suspect, ignored
        $this->ping('2026-09-25 10:35', 27.70200);          // +111 m
        $this->ping('2026-09-24 17:00', 27.60000);          // yesterday, not in today's route

        $route = app(RouteSummarizer::class)->summarise($this->tso, Carbon::parse('2026-09-25', self::TZ));

        $this->assertEqualsWithDelta(0.22, $route->distance_km, 0.01);
        $this->assertSame(20, $route->idle_minutes);
        $this->assertSame(7, $route->ping_count);
        $this->assertSame(5, $route->kept_count);
        $this->assertCount(3, $route->path);
        $this->assertSame('2026-09-25 10:00', $route->first_ping_at->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_summarising_again_updates_the_same_row(): void
    {
        $this->ping('2026-09-25 10:00', 27.70000);
        $this->ping('2026-09-25 10:10', 27.70100);
        app(RouteSummarizer::class)->summarise($this->tso, Carbon::parse('2026-09-25', self::TZ));

        $this->ping('2026-09-25 10:20', 27.70200);
        $route = app(RouteSummarizer::class)->summarise($this->tso, Carbon::parse('2026-09-25', self::TZ));

        $this->assertSame(1, DailyRoute::query()->count());
        $this->assertEqualsWithDelta(0.22, $route->distance_km, 0.01);
    }

    public function test_command_summarises_everyone_with_pings_today(): void
    {
        $other = $this->makeUser('TSO', ['RD002']);
        $this->ping('2026-09-25 10:00', 27.70000);
        $this->ping('2026-09-25 10:10', 27.70100, user: $other);

        $this->artisan('fs:summarise-routes')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$this->tso->id, $other->id], DailyRoute::query()->pluck('user_id')->all());
    }

    public function test_prune_deletes_only_history_older_than_the_retention_window(): void
    {
        config(['field_sales.tracking.retention_days' => 90]);
        $this->ping('2026-06-20 10:00', 27.7);  // 97 days old
        $this->ping('2026-07-01 10:00', 27.7);  // 86 days old

        $this->artisan('fs:prune-locations')->assertSuccessful();

        $this->assertSame(['2026-07-01'], LocationPing::query()->get()->map(fn ($p) => $p->recorded_at->setTimezone(self::TZ)->toDateString())->all());
    }
}
