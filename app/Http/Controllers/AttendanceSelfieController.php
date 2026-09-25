<?php

namespace App\Http\Controllers;

use App\Models\TsoAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSelfieController extends Controller
{
    /** Serves a punch selfie to its owner or to anyone who can view all attendance. */
    public function __invoke(Request $request, TsoAttendance $attendance, string $type): StreamedResponse
    {
        $user = $request->user();
        abort_unless($attendance->user_id === $user?->id || $user?->can('attendance.view_all'), 403);
        abort_unless($attendance->hasSelfie($type), 404);

        return Storage::disk(TsoAttendance::SELFIE_DISK)->response($attendance->selfiePath($type), headers: [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
