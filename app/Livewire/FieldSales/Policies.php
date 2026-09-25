<?php

namespace App\Livewire\FieldSales;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\AttendancePolicy;
use App\Models\FieldSales\Holiday;
use App\Models\FieldSales\Region;
use App\Services\FieldSales\PolicyResolver;
use App\Support\FieldSalesConfig;
use App\Support\NepaliDate;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Field Sales Setup → Duty Rules & Holidays: the company duty policy, Area
 * overrides, the holiday calendar and the ASM daily summary email.
 */
#[Layout('components.layouts.app')]
#[Title('Duty Rules & Holidays')]
class Policies extends Component
{
    /** '' = company default, otherwise an area id. */
    public string $scope = '';

    public string $dutyStart = '09:30';

    public string $dutyEnd = '18:00';

    public int $lateGraceMinutes = 15;

    public int $halfDayBelowMinutes = 240;

    /** @var list<int|string> */
    public array $weeklyOff = [6];

    public string $geofenceMode = 'flag';

    public int $pingIntervalMinutes = 5;

    public string $holidayDate = '';

    public string $holidayName = '';

    public ?int $holidayRegionId = null;

    public string $holidayYear = '';

    public bool $summaryEnabled = false;

    public string $summaryTime = '19:00';

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizeSetup();
        $this->loadPolicy();
        $this->holidayYear = (string) Carbon::now(config('field_sales.timezone'))->year;

        $settings = FieldSalesConfig::all();
        $this->summaryEnabled = $settings['daily_summary_enabled'];
        $this->summaryTime = $settings['daily_summary_time'];
    }

    public function updatedScope(): void
    {
        $this->loadPolicy();
    }

    private function loadPolicy(): void
    {
        $policy = $this->scope === ''
            ? app(PolicyResolver::class)->companyDefault()
            : (AttendancePolicy::query()->where('area_id', (int) $this->scope)->first() ?? app(PolicyResolver::class)->companyDefault());

        $this->dutyStart = $policy->dutyStartTime();
        $this->dutyEnd = $policy->dutyEndTime();
        $this->lateGraceMinutes = $policy->late_grace_minutes;
        $this->halfDayBelowMinutes = $policy->half_day_below_minutes;
        $this->weeklyOff = $policy->weeklyOffDays();
        $this->geofenceMode = $policy->geofence_mode;
        $this->pingIntervalMinutes = $policy->ping_interval_minutes;
        $this->resetValidation();
    }

    public function savePolicy(): void
    {
        $this->authorizeSetup();
        $this->validate([
            'scope' => ['nullable', 'exists:fs_areas,id'],
            'dutyStart' => ['required', 'date_format:H:i'],
            'dutyEnd' => ['required', 'date_format:H:i', 'after:dutyStart'],
            'lateGraceMinutes' => ['required', 'integer', 'between:0,240'],
            'halfDayBelowMinutes' => ['required', 'integer', 'between:0,720'],
            'weeklyOff' => ['array'],
            'weeklyOff.*' => ['integer', 'between:0,6'],
            'geofenceMode' => ['required', 'in:'.implode(',', AttendancePolicy::GEOFENCE_MODES)],
            'pingIntervalMinutes' => ['required', 'integer', 'between:1,60'],
        ], [], [
            'dutyStart' => 'duty start', 'dutyEnd' => 'duty end', 'lateGraceMinutes' => 'grace period',
            'halfDayBelowMinutes' => 'half-day threshold', 'pingIntervalMinutes' => 'tracking interval',
        ]);

        AttendancePolicy::query()->updateOrCreate(
            ['area_id' => $this->scope === '' ? null : (int) $this->scope],
            [
                'duty_start' => $this->dutyStart,
                'duty_end' => $this->dutyEnd,
                'late_grace_minutes' => $this->lateGraceMinutes,
                'half_day_below_minutes' => $this->halfDayBelowMinutes,
                'weekly_off' => array_values(array_unique(array_map('intval', $this->weeklyOff))),
                'geofence_mode' => $this->geofenceMode,
                'ping_interval_minutes' => $this->pingIntervalMinutes,
            ],
        );

        $this->flash = 'Duty rules saved. Past days update on the next hourly recalculation.';
    }

    public function removeOverride(int $areaId): void
    {
        $this->authorizeSetup();
        AttendancePolicy::query()->where('area_id', $areaId)->delete();
        if ($this->scope === (string) $areaId) {
            $this->loadPolicy();
        }
        $this->flash = 'Area override removed; the company rules apply again.';
    }

    public function addHoliday(): void
    {
        $this->authorizeSetup();
        $this->validate([
            'holidayDate' => ['required', 'date'],
            'holidayName' => ['required', 'string', 'max:120'],
            'holidayRegionId' => ['nullable', 'exists:fs_regions,id'],
        ], [], ['holidayDate' => 'date', 'holidayName' => 'name']);

        $exists = Holiday::query()->whereDate('holiday_date', $this->holidayDate)
            ->where('region_id', $this->holidayRegionId)->exists();
        if ($exists) {
            $this->addError('holidayDate', 'A holiday already exists on this date for that region.');

            return;
        }

        Holiday::query()->create([
            'holiday_date' => $this->holidayDate,
            'name' => trim($this->holidayName),
            'region_id' => $this->holidayRegionId,
        ]);

        $this->reset('holidayDate', 'holidayName', 'holidayRegionId');
        $this->flash = 'Holiday added.';
    }

    public function deleteHoliday(int $id): void
    {
        $this->authorizeSetup();
        Holiday::findOrFail($id)->delete();
        $this->flash = 'Holiday removed.';
    }

    public function saveSummary(): void
    {
        $this->authorizeSetup();
        $this->validate(['summaryTime' => ['required', 'date_format:H:i'], 'summaryEnabled' => ['boolean']]);

        FieldSalesConfig::save(['daily_summary_enabled' => $this->summaryEnabled, 'daily_summary_time' => $this->summaryTime]);
        $this->flash = 'Daily summary email settings saved.';
    }

    private function authorizeSetup(): void
    {
        abort_unless(auth()->user()?->can('fs.setup.manage'), 403);
    }

    public function render()
    {
        $year = (int) $this->holidayYear ?: (int) date('Y');

        return view('livewire.field-sales.policies', [
            'areas' => Area::query()->orderBy('name')->pluck('name', 'id'),
            'overrides' => AttendancePolicy::query()->whereNotNull('area_id')->with('area')->get(),
            'regions' => Region::query()->orderBy('name')->pluck('name', 'id'),
            'holidays' => Holiday::query()->with('region')
                ->whereYear('holiday_date', $year)
                ->orderBy('holiday_date')->get(),
            'weekdays' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'bs' => fn ($date) => NepaliDate::format($date),
        ]);
    }
}
