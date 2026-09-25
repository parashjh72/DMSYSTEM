<?php

namespace App\Livewire\FieldSales;

use App\Models\FieldSales\AttendanceDay;
use App\Models\FieldSales\DailyRoute;
use App\Models\TsoAttendance;
use App\Models\User;
use App\Services\FieldSales\FieldStaff;
use App\Services\FieldSales\RouteSummarizer;
use App\Support\NepaliDate;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Route Playback')]
class RoutePlayback extends Component
{
    #[Url]
    public ?int $user = null;

    #[Url]
    public string $date = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('fs.map.view'), 403);
        $this->date = $this->date ?: Carbon::now(config('field_sales.timezone'))->toDateString();
    }

    public function render(FieldStaff $staff, RouteSummarizer $summarizer)
    {
        $tz = config('field_sales.timezone');
        $viewer = auth()->user();
        $options = $staff->query($viewer)->pluck('name', 'id');
        $subject = $this->user && $options->has($this->user) ? User::find($this->user) : null;
        $date = rescue(fn () => Carbon::parse($this->date, $tz), Carbon::now($tz), report: false);

        $route = null;
        $attendance = null;
        $day = null;

        if ($subject) {
            $route = DailyRoute::query()->where('user_id', $subject->id)->whereDate('route_date', $date->toDateString())->first();
            if ($route === null || $date->isToday()) {
                $route = $summarizer->summarise($subject, $date);
            }
            $attendance = TsoAttendance::query()->where('user_id', $subject->id)->whereDate('attendance_date', $date->toDateString())->first();
            $day = AttendanceDay::query()->with('checkInGeofence')->where('user_id', $subject->id)->whereDate('attendance_date', $date->toDateString())->first();
        }

        $punches = [];
        foreach (['check_in' => 'Check-in', 'check_out' => 'Check-out'] as $type => $label) {
            if ($attendance?->{"{$type}_latitude"}) {
                $punches[] = [
                    'label' => $label.' '.$attendance->{"{$type}_at"}->setTimezone($tz)->format('H:i'),
                    'lat' => (float) $attendance->{"{$type}_latitude"},
                    'lng' => (float) $attendance->{"{$type}_longitude"},
                    'type' => $type,
                ];
            }
        }

        return view('livewire.field-sales.route-playback', [
            'staffOptions' => $options,
            'subject' => $subject,
            'route' => $route,
            'attendance' => $attendance,
            'day' => $day,
            'punches' => $punches,
            'path' => $route?->path ?? [],
            'tz' => $tz,
            'bsDate' => NepaliDate::formatLong($date),
        ]);
    }
}
