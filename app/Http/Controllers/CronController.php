<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

/**
 * HTTP trigger for the scheduler, for hosts whose cron can only run curl/wget.
 * Enabled only when CRON_TOKEN is set; the token must match exactly.
 *
 *   * * * * * curl -s https://your-domain/cron/<CRON_TOKEN> >/dev/null
 */
class CronController extends Controller
{
    public function __invoke(string $token)
    {
        $secret = (string) config('app.cron_token');

        abort_if($secret === '' || ! hash_equals($secret, $token), 404);

        @set_time_limit(120);
        @ignore_user_abort(true);

        if (request()->has('clear_cache')) {
            Artisan::call('optimize:clear');
            return response('Cache cleared: ' . Artisan::output(), 200);
        }

        if (request()->has('clear_madan_attendance')) {
            $user = \App\Models\User::where('name', 'like', '%madan%')
                ->orWhere('email', 'like', '%madan%')
                ->first();

            if (! $user) {
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'No user matching madan found',
                    'all_users' => \App\Models\User::select('id', 'name', 'email')->get(),
                ]);
            }

            $deleted = \App\Models\TsoAttendance::where('user_id', $user->id)->delete();

            return response()->json([
                'status' => 'ok',
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'deleted_attendance_records' => $deleted,
            ]);
        }

        if (request()->has('view_attendances')) {
            return response()->json([
                'rows' => \App\Models\TsoAttendance::with('user:id,name,email')->latest()->take(20)->get(),
            ]);
        }



        try {
            Artisan::call('schedule:run');

            return response(trim(Artisan::output()) ?: 'ok', 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            return response("Exception: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }
}
