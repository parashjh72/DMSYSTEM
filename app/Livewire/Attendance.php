<?php

namespace App\Livewire;

use App\Services\AttendanceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Attendance')]
class Attendance extends Component
{
    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('attendance.check'), 403);
    }

    /** Called from Alpine after navigator.geolocation resolves. Coords never come from a form field. */
    public function checkIn(AttendanceService $service, float $latitude, float $longitude, ?float $accuracy = null): void
    {
        abort_unless(auth()->user()?->can('attendance.check'), 403);
        $this->error = null;

        try {
            $service->checkIn(auth()->user(), compact('latitude', 'longitude', 'accuracy'));
            session()->flash('status', 'Checked in.');
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function checkOut(AttendanceService $service, float $latitude, float $longitude, ?float $accuracy = null): void
    {
        abort_unless(auth()->user()?->can('attendance.check'), 403);
        $this->error = null;

        try {
            $service->checkOut(auth()->user(), compact('latitude', 'longitude', 'accuracy'));
            session()->flash('status', 'Checked out.');
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function reportGpsError(string $message): void
    {
        $this->error = $message;
    }

    public function render(AttendanceService $service)
    {
        return view('livewire.attendance', [
            'record' => $service->todayFor(auth()->user()),
            'today' => $service->today(),
            'poorAccuracy' => (int) config('attendance.poor_accuracy_metres'),
        ]);
    }
}
