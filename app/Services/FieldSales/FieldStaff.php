<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\Area;
use App\Models\RetailDistributor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a manager may see, and which Area a field user belongs to. Visibility
 * follows the existing Attendance Report rule: Super Admin / Admin / NSM see
 * everyone, anyone else sees their direct reports and themselves. Areas group
 * distributors, so a user's Area comes from their scoped RD codes.
 */
class FieldStaff
{
    public const NATIONAL_ROLES = ['Super Admin', 'Admin', 'NSM'];

    /** @var array<string, Area|null> areas already looked up, by sorted RD-code list */
    private array $areas = [];

    /** @return list<int>|null  null = unrestricted */
    public function visibleUserIds(User $viewer): ?array
    {
        if ($viewer->hasAnyRole(self::NATIONAL_ROLES)) {
            return null;
        }

        return $viewer->subordinates()->pluck('id')->push($viewer->id)->all();
    }

    public function canSee(User $viewer, User $subject): bool
    {
        $visible = $this->visibleUserIds($viewer);

        return $visible === null || in_array($subject->id, $visible, true);
    }

    /**
     * Field users (TSO role) the viewer may see, optionally narrowed to a region,
     * area or distributor.
     *
     * @return Builder<User>
     */
    public function query(User $viewer, ?int $regionId = null, ?int $areaId = null, ?string $rdCode = null): Builder
    {
        $visible = $this->visibleUserIds($viewer);
        $codes = $this->distributorCodes($regionId, $areaId, $rdCode);

        return User::query()
            ->role('TSO')
            ->when($visible !== null, fn (Builder $q) => $q->whereIn('id', $visible))
            ->when($codes !== null, function (Builder $q) use ($codes) {
                $q->where(function (Builder $inner) use ($codes) {
                    $inner->whereRaw('1 = 0');
                    foreach ($codes as $code) {
                        $inner->orWhereJsonContains('scoped_rd_codes', $code);
                    }
                });
            })
            ->orderBy('name');
    }

    /**
     * RD codes covered by a region / area / distributor filter; null = no filter.
     *
     * @return list<string>|null
     */
    public function distributorCodes(?int $regionId, ?int $areaId, ?string $rdCode): ?array
    {
        if ($rdCode !== null && $rdCode !== '') {
            return [$rdCode];
        }

        if (! $regionId && ! $areaId) {
            return null;
        }

        return RetailDistributor::query()
            ->join('fs_area_distributors', 'fs_area_distributors.retail_distributor_id', '=', 'retail_distributors.id')
            ->join('fs_areas', 'fs_areas.id', '=', 'fs_area_distributors.area_id')
            ->when($areaId, fn ($q, $v) => $q->where('fs_areas.id', $v))
            ->when($regionId, fn ($q, $v) => $q->where('fs_areas.region_id', $v))
            ->pluck('retail_distributors.code')
            ->all();
    }

    /** The Area of the user's first scoped distributor that has one. */
    public function areaFor(User $user): ?Area
    {
        $codes = $user->scopedRdCodes();
        if ($codes === []) {
            return null;
        }
        sort($codes);

        return $this->areas[implode('|', $codes)] ??= Area::query()
            ->select('fs_areas.*')
            ->join('fs_area_distributors', 'fs_area_distributors.area_id', '=', 'fs_areas.id')
            ->join('retail_distributors', 'retail_distributors.id', '=', 'fs_area_distributors.retail_distributor_id')
            ->whereIn('retail_distributors.code', $codes)
            ->orderBy('fs_areas.id')
            ->first();
    }
}
