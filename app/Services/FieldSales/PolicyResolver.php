<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\AttendancePolicy;
use App\Models\User;

/**
 * Picks the duty policy for a field user: their Area's policy, else the
 * company default row, else the config defaults.
 */
class PolicyResolver
{
    /** @var array<int|string, AttendancePolicy> resolved policies by area id ('default' for none) */
    private array $resolved = [];

    public function __construct(private FieldStaff $staff) {}

    public function forUser(User $user): AttendancePolicy
    {
        $areaId = $this->staff->areaFor($user)?->id;
        $key = $areaId ?? 'default';

        return $this->resolved[$key] ??= ($areaId ? AttendancePolicy::query()->where('area_id', $areaId)->first() : null)
            ?? $this->companyDefault();
    }

    public function companyDefault(): AttendancePolicy
    {
        return $this->resolved['default'] ??= AttendancePolicy::query()->whereNull('area_id')->first()
            ?? AttendancePolicy::fromDefaults();
    }
}
