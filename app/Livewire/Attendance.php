<?php

namespace App\Livewire;

use App\Models\PjpDay;
use App\Models\TsoAttendance;
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
        abort_unless(auth()->user()?->can('attendance.self'), 403);
    }

    /** Called from Alpine after navigator.geolocation resolves. Coords never come from a form field. */
    public function checkIn(AttendanceService $service, float $latitude, float $longitude, ?float $accuracy = null, ?array $telemetry = null): void
    {
        abort_unless(auth()->user()?->can('attendance.self'), 403);
        $this->error = null;

        if (empty($telemetry['samples']) || ! is_array($telemetry['samples']) || count($telemetry['samples']) < 1) {
            $this->error = 'Live GPS verification required. Please tap punch and wait for location acquisition.';
            return;
        }

        try {
            $gps = compact('latitude', 'longitude', 'accuracy');
            $gps['telemetry'] = $telemetry;
            $service->checkIn(auth()->user(), $gps);
            session()->flash('status', 'Checked in successfully.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Attendance checkIn error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'exception' => get_class($e),
            ]);
            $this->error = $e->getMessage();
        }
    }

    public function checkOut(AttendanceService $service, float $latitude, float $longitude, ?float $accuracy = null, ?array $telemetry = null): void
    {
        abort_unless(auth()->user()?->can('attendance.self'), 403);
        $this->error = null;

        if (empty($telemetry['samples']) || ! is_array($telemetry['samples']) || count($telemetry['samples']) < 1) {
            $this->error = 'Live GPS verification required. Please tap punch and wait for location acquisition.';
            return;
        }

        try {
            $gps = compact('latitude', 'longitude', 'accuracy');
            $gps['telemetry'] = $telemetry;
            $service->checkOut(auth()->user(), $gps);
            session()->flash('status', 'Checked out successfully.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Attendance checkOut error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'exception' => get_class($e),
            ]);
            $this->error = $e->getMessage();
        }
    }

    public function reportGpsError(string $message): void
    {
        \Illuminate\Support\Facades\Log::warning('Attendance GPS Error: ' . $message, [
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
        ]);
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
        $user = auth()->user();

        return view('livewire.attendance', [
            'record' => $service->todayFor($user),
            'today' => $service->today(),
            'poorAccuracy' => (int) config('attendance.poor_accuracy_metres'),
            'upcoming' => $this->upcoming(),
            'recentHistory' => TsoAttendance::query()
                ->where('user_id', $user->id)
                ->whereDate('attendance_date', '<', $service->today())
                ->orderByDesc('attendance_date')
                ->take(7)
                ->get(),
        ]);
    }
}
