<?php

namespace App\Services\FieldSales;

use App\Models\FieldSales\Geofence;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Checks a punch location against the user's allowed check-in points: their
 * distributors' points, their Area's unlinked points (offices) and any
 * company-wide unlinked point.
 */
class GeofenceService
{
    public function __construct(private FieldStaff $staff, private PolicyResolver $policies) {}

    /** @return Collection<int, Geofence> */
    public function pointsFor(User $user): Collection
    {
        $codes = $user->scopedRdCodes();
        $areaId = $this->staff->areaFor($user)?->id;

        return Geofence::query()
            ->where('active', true)
            ->where(function ($q) use ($codes, $areaId) {
                $q->whereNull('retail_distributor_id')
                    ->where(fn ($office) => $office->whereNull('area_id')->when($areaId, fn ($a) => $a->orWhere('area_id', $areaId)));

                if ($codes !== []) {
                    $q->orWhereHas('distributor', fn ($d) => $d->whereIn('code', $codes));
                }
            })
            ->get();
    }

    /**
     * @return array{status: 'inside'|'outside'|'none', geofence: ?Geofence, distance: ?int}
     */
    public function evaluate(User $user, float $latitude, float $longitude): array
    {
        $nearest = null;
        $nearestDistance = null;

        foreach ($this->pointsFor($user) as $point) {
            $distance = AttendanceService::distanceMetres($latitude, $longitude, $point->latitude, $point->longitude);
            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearest = $point;
                $nearestDistance = $distance;
            }
        }

        if ($nearest === null) {
            return ['status' => 'none', 'geofence' => null, 'distance' => null];
        }

        return [
            'status' => $nearestDistance <= $nearest->radius_metres ? 'inside' : 'outside',
            'geofence' => $nearest,
            'distance' => (int) round($nearestDistance),
        ];
    }

    /**
     * Evaluates the punch and, when the user's policy is in block mode, refuses
     * a punch outside every allowed point.
     *
     * @return array{status: 'inside'|'outside'|'none', geofence: ?Geofence, distance: ?int}|null null when geofencing is off
     */
    public function assessPunch(User $user, float $latitude, float $longitude): ?array
    {
        $mode = $this->policies->forUser($user)->geofence_mode;
        if ($mode === 'off') {
            return null;
        }

        $result = $this->evaluate($user, $latitude, $longitude);

        if ($mode === 'block' && $result['status'] === 'outside') {
            throw new RuntimeException(sprintf(
                'You are %s m from %s. Attendance can only be punched within %d m of an assigned point.',
                number_format($result['distance']), $result['geofence']->name, $result['geofence']->radius_metres,
            ));
        }

        return $result;
    }
}
