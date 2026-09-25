<?php

namespace App\Http\Controllers\FieldSales;

use App\Http\Controllers\Controller;
use App\Services\FieldSales\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints used by the field app (PWA today, native app later) for
 * duty-hours location tracking. Session-authenticated; field users (TSO) only.
 */
class TrackingController extends Controller
{
    public function __construct(private TrackingService $tracking) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->tracking->status($request->user()));
    }

    public function consent(Request $request): JsonResponse
    {
        $this->tracking->giveConsent($request->user(), $request->ip(), $request->userAgent());

        return response()->json($this->tracking->status($request->user()));
    }

    public function revokeConsent(Request $request): JsonResponse
    {
        $this->tracking->revokeConsent($request->user());

        return response()->json($this->tracking->status($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pings' => ['required', 'array', 'min:1', 'max:'.config('field_sales.tracking.max_batch')],
            'pings.*.id' => ['required', 'uuid'],
            'pings.*.t' => ['required', 'numeric', 'min:0'],
            'pings.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'pings.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'pings.*.acc' => ['nullable', 'numeric', 'min:0'],
            'pings.*.speed' => ['nullable', 'numeric', 'min:0'],
            'pings.*.battery' => ['nullable', 'integer', 'between:0,100'],
        ]);

        if (! $this->tracking->hasConsent($request->user())) {
            return response()->json([
                'message' => 'Location tracking consent is required.',
                'code' => 'consent_required',
            ], 403);
        }

        return response()->json($this->tracking->ingest($request->user(), $validated['pings']));
    }
}
