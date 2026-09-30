<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationPingsRequest;
use App\Services\LocationTrackingService;
use Illuminate\Http\JsonResponse;

/**
 * Receives the GPS breadcrumbs a field user's phone queued during duty —
 * live, or synced late after an offline stretch. Each ping is filed under the
 * duty period it was taken in; `tracking: false` tells the client the user is
 * no longer checked in, so it stops collecting.
 */
class LocationPingController extends Controller
{
    public function __invoke(StoreLocationPingsRequest $request, LocationTrackingService $tracking): JsonResponse
    {
        if (! config('tracking.enabled')) {
            return response()->json(['tracking' => false, 'accepted' => 0]);
        }

        return response()->json([
            'accepted' => $tracking->recordForUser($request->user(), $request->validated('pings')),
            'tracking' => $tracking->openAttendanceFor($request->user()) !== null,
        ]);
    }
}
