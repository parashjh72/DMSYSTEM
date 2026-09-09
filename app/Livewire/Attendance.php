<?php

namespace App\Livewire;

use App\Models\PjpDay;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;
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

    /** This TSO's planned days from today onward, next 14 days, with retailers + visit progress. */
    private function upcoming()
    {
        $user = auth()->user();
        if (! $user?->can('pjp.create')) {
            return collect();
        }

        $tz = config('pjp.timezone');
        $from = Carbon::now($tz)->startOfDay();
        $to = $from->copy()->addDays(14);

        return PjpDay::query()
            ->with(['retailers', 'visits'])
            ->whereHas('pjp', fn ($q) => $q->where('tso_id', $user->id))
            ->where('day_status', 'planned')
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])
            ->has('retailers')
            ->orderBy('plan_date')
            ->get()
            ->map(function (PjpDay $d) {
                $d->visited_codes = $d->visits->pluck('rt_code')->all();

                return $d;
            });
    }

    public function render(AttendanceService $service)
    {
        return view('livewire.attendance', [
            'record' => $service->todayFor(auth()->user()),
            'today' => $service->today(),
            'poorAccuracy' => (int) config('attendance.poor_accuracy_metres'),
            'upcoming' => $this->upcoming(),
        ]);
    }
}
