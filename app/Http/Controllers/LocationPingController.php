<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationPingsRequest;
use App\Services\LocationTrackingService;
use Illuminate\Http\JsonResponse;

/**
 * Receives the GPS breadcrumbs a checked-in field user's browser queues in the
 * background. Answers `tracking: false` once the user is no longer checked in,
 * which tells the client to stop.
 */
class LocationPingController extends Controller
{
    public function __invoke(StoreLocationPingsRequest $request, LocationTrackingService $tracking): JsonResponse
    {
        $attendance = config('tracking.enabled') ? $tracking->openAttendanceFor($request->user()) : null;

        if (! $attendance) {
            return response()->json(['tracking' => false, 'accepted' => 0], 409);
        }

        return response()->json([
            'tracking' => true,
            'accepted' => $tracking->record($attendance, $request->validated('pings')),
        ]);
    }
}
