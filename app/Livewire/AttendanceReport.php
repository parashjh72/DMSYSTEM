<?php

namespace App\Livewire;

use App\Models\TsoAttendance;
use App\Models\User;
use App\Services\Reporting\FilterOptions;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Attendance Report')]
class AttendanceReport extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $tsoId = null;

    #[Url]
    public ?int $asmId = null;

    #[Url]
    public string $rdCode = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('attendance.view_all'), 403);
        $this->from = $this->from ?: Carbon::now(config('attendance.timezone'))->startOfMonth()->toDateString();
        $this->to = $this->to ?: Carbon::now(config('attendance.timezone'))->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** ASMs see only their own line; Admin / NSM see everyone. */
    private function visibleUserIds(): ?array
    {
        $me = auth()->user();
        if ($me->hasAnyRole(['Super Admin', 'Admin', 'NSM'])) {
            return null;
        }

        return $me->subordinates()->pluck('id')->push($me->id)->all();
    }

    private function baseQuery()
    {
        $visible = $this->visibleUserIds();

        return TsoAttendance::query()
            ->with('user.reportsTo')
            ->when($visible !== null, fn ($q) => $q->whereIn('user_id', $visible))
            ->when($this->from, fn ($q, $v) => $q->whereDate('attendance_date', '>=', $v))
            ->when($this->to, fn ($q, $v) => $q->whereDate('attendance_date', '<=', $v))
            ->when($this->tsoId, fn ($q, $v) => $q->where('user_id', $v))
            ->when($this->asmId, fn ($q, $v) => $q->whereIn('user_id',
                User::where('reports_to_id', $v)->pluck('id')))
            ->when($this->rdCode, fn ($q, $v) => $q->whereIn('user_id',
                User::whereJsonContains('scoped_rd_codes', $v)->pluck('id')))
            ->when($this->status, fn ($q, $v) => $q->where('status', $v));
    }

    public function export()
    {
        abort_unless(auth()->user()?->can('exports.view'), 403);
        $tz = config('attendance.timezone');

        $rows = $this->baseQuery()->orderByDesc('attendance_date')->orderBy('user_id')->get();
        $header = ['Date', 'TSO', 'ASM', 'Check In', 'In Lat', 'In Lng', 'In Accuracy', 'In Address',
            'Check Out', 'Out Lat', 'Out Lng', 'Out Address', 'Working Duration', 'Status'];

        return response()->streamDownload(function () use ($rows, $header, $tz) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->attendance_date->format('Y-m-d'),
                    $r->user?->name, $r->user?->reportsTo?->name,
                    $r->check_in_at?->timezone($tz)->format('H:i'),
                    $r->check_in_latitude, $r->check_in_longitude, $r->check_in_accuracy, $r->check_in_address,
                    $r->check_out_at?->timezone($tz)->format('H:i'),
                    $r->check_out_latitude, $r->check_out_longitude, $r->check_out_address,
                    $r->workingLabel(), $r->status,
                ], ',', '"', '');
            }
            fclose($out);
        }, 'attendance-'.$this->from.'_'.$this->to.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(FilterOptions $options)
    {
        return view('livewire.attendance-report', [
            'rows' => $this->baseQuery()->orderByDesc('attendance_date')->orderBy('user_id')->paginate(50),
            'tsoOptions' => User::role('TSO')->orderBy('name')->pluck('name', 'id'),
            'asmOptions' => User::role('ASM')->orderBy('name')->pluck('name', 'id'),
            'rdOptions' => $options->distributors(),
            'tz' => config('attendance.timezone'),
        ]);
    }
}
