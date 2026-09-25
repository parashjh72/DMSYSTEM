<?php

namespace App\Livewire;

use App\Models\FieldSales\AttendanceDay;
use App\Models\PjpDay;
use App\Models\TsoAttendance;
use App\Services\AttendanceService;
use App\Services\FieldSales\TrackingService;
use App\Support\NepaliDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Attendance')]
class Attendance extends Component
{
    use WithFileUploads;

    public ?string $error = null;

    /** Live front-camera photo taken in the browser just before the punch. */
    public ?TemporaryUploadedFile $selfie = null;

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

        if (! $this->selfieIsValid()) {
            return;
        }

        try {
            $gps = compact('latitude', 'longitude', 'accuracy');
            $gps['telemetry'] = $telemetry;
            DB::transaction(fn () => $this->storeSelfie($service->checkIn(auth()->user(), $gps), 'check_in'));
            session()->flash('status', 'Checked in successfully.');
        } catch (\Throwable $e) {
            Log::warning('Attendance checkIn error: '.$e->getMessage(), [
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

        if (! $this->selfieIsValid()) {
            return;
        }

        try {
            $gps = compact('latitude', 'longitude', 'accuracy');
            $gps['telemetry'] = $telemetry;
            DB::transaction(fn () => $this->storeSelfie($service->checkOut(auth()->user(), $gps), 'check_out'));
            session()->flash('status', 'Checked out successfully.');
        } catch (\Throwable $e) {
            Log::warning('Attendance checkOut error: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'exception' => get_class($e),
            ]);
            $this->error = $e->getMessage();
        }
    }

    /** Validates the punch selfie, putting the first problem into the error banner. */
    private function selfieIsValid(): bool
    {
        if (! config('attendance.selfie.required') && $this->selfie === null) {
            return true;
        }

        $validator = Validator::make(['selfie' => $this->selfie], [
            'selfie' => ['required', 'image', 'max:'.config('attendance.selfie.max_kb')],
        ], [
            'selfie.required' => 'A live selfie is required to punch attendance. Please allow camera access and take a photo.',
            'selfie.image' => 'The selfie must be a photo.',
            'selfie.max' => 'The selfie is too large. Please try again.',
        ]);

        if ($validator->fails()) {
            $this->error = $validator->errors()->first('selfie');

            return false;
        }

        return true;
    }

    /** @param  'check_in'|'check_out'  $type */
    private function storeSelfie(TsoAttendance $record, string $type): void
    {
        if ($this->selfie === null) {
            return;
        }

        $this->selfie->storeAs(dirname($record->selfiePath($type)), basename($record->selfiePath($type)), TsoAttendance::SELFIE_DISK);
        $this->selfie = null;
    }

    /** Field Sales: the officer agrees to duty-hours location sharing. */
    public function acceptTrackingConsent(TrackingService $tracking): void
    {
        abort_unless(auth()->user()?->can('field-sales.track'), 403);
        $tracking->giveConsent(auth()->user(), request()->ip(), request()->userAgent());
    }

    public function revokeTrackingConsent(TrackingService $tracking): void
    {
        abort_unless(auth()->user()?->can('field-sales.track'), 403);
        $tracking->revokeConsent(auth()->user());
    }

    public function reportGpsError(string $message): void
    {
        Log::warning('Attendance GPS Error: '.$message, [
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

    public function render(AttendanceService $service, TrackingService $tracking)
    {
        $user = auth()->user();

        return view('livewire.attendance', [
            'bsToday' => NepaliDate::formatLong($service->today()),
            'fieldTracking' => $tracking->status($user),
            'fieldDay' => AttendanceDay::query()->with('checkInGeofence')
                ->where('user_id', $user->id)
                ->whereDate('attendance_date', $service->today()->toDateString())
                ->first(),
            'record' => $service->todayFor($user),
            'today' => $service->today(),
            'poorAccuracy' => (int) config('attendance.poor_accuracy_metres'),
            'selfieRequired' => (bool) config('attendance.selfie.required'),
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
