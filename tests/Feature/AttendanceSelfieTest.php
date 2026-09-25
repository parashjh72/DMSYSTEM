<?php

namespace Tests\Feature;

use App\Livewire\Attendance;
use App\Models\TsoAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceSelfieTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    /** @var array{samples: array<int, array{lat: float, lng: float, accuracy: float, t: int}>} */
    protected array $telemetry = [
        'samples' => [
            ['lat' => 27.7172450, 'lng' => 85.3240450, 'accuracy' => 10.0, 't' => 1000],
            ['lat' => 27.7172461, 'lng' => 85.3240437, 'accuracy' => 9.0, 't' => 2000],
            ['lat' => 27.7172473, 'lng' => 85.3240442, 'accuracy' => 8.0, 't' => 3000],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance.reverse_geocode' => false, 'attendance.selfie.required' => true]);
        Storage::fake(TsoAttendance::SELFIE_DISK);

        Role::findOrCreate('TSO');
        $this->user = User::factory()->create();
        $this->user->assignRole('TSO');
    }

    public function test_check_in_stores_the_selfie(): void
    {
        Livewire::actingAs($this->user)
            ->test(Attendance::class)
            ->set('selfie', UploadedFile::fake()->image('selfie.jpg'))
            ->call('checkIn', 27.7172473, 85.3240442, 8.0, $this->telemetry)
            ->assertSet('error', null);

        $record = TsoAttendance::where('user_id', $this->user->id)->sole();
        Storage::disk(TsoAttendance::SELFIE_DISK)->assertExists($record->selfiePath('check_in'));
    }

    public function test_check_in_without_selfie_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test(Attendance::class)
            ->call('checkIn', 27.7172473, 85.3240442, 8.0, $this->telemetry)
            ->assertSee('A live selfie is required');

        $this->assertDatabaseCount('tso_attendances', 0);
    }

    public function test_check_in_without_selfie_is_allowed_when_not_required(): void
    {
        config(['attendance.selfie.required' => false]);

        Livewire::actingAs($this->user)
            ->test(Attendance::class)
            ->call('checkIn', 27.7172473, 85.3240442, 8.0, $this->telemetry)
            ->assertSet('error', null);

        $this->assertDatabaseCount('tso_attendances', 1);
    }

    public function test_check_out_stores_the_selfie(): void
    {
        $record = TsoAttendance::create([
            'user_id' => $this->user->id,
            'attendance_date' => now(config('attendance.timezone'))->toDateString(),
            'check_in_at' => now()->subHours(8),
            'check_in_latitude' => 27.7100000,
            'check_in_longitude' => 85.3100000,
            'check_in_accuracy' => 10.0,
            'status' => 'checked_in',
        ]);

        Livewire::actingAs($this->user)
            ->test(Attendance::class)
            ->set('selfie', UploadedFile::fake()->image('selfie.jpg'))
            ->call('checkOut', 27.7172473, 85.3240442, 8.0, $this->telemetry)
            ->assertSet('error', null);

        $this->assertTrue($record->refresh()->isCheckedOut());
        Storage::disk(TsoAttendance::SELFIE_DISK)->assertExists($record->selfiePath('check_out'));
    }

    public function test_selfie_is_visible_to_owner_and_managers_only(): void
    {
        $record = TsoAttendance::create([
            'user_id' => $this->user->id,
            'attendance_date' => now(config('attendance.timezone'))->toDateString(),
            'check_in_at' => now(),
            'status' => 'checked_in',
        ]);
        Storage::disk(TsoAttendance::SELFIE_DISK)->put($record->selfiePath('check_in'), 'jpeg-bytes');
        $url = route('attendance.selfie', [$record, 'check_in']);

        $this->actingAs($this->user)->get($url)->assertOk();

        $colleague = User::factory()->create();
        $colleague->assignRole('TSO');
        $this->actingAs($colleague)->get($url)->assertForbidden();

        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findOrCreate('attendance.view_all'));
        $this->actingAs($manager)->get($url)->assertOk();

        $this->actingAs($manager)->get(route('attendance.selfie', [$record, 'check_out']))->assertNotFound();
    }
}
