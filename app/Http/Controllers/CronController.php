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

        if (request()->has('git_pull')) {
            $output = [];
            @exec('cd ' . base_path() . ' && git pull origin main 2>&1', $output, $code);
            Artisan::call('optimize:clear');
            return response()->json([
                'status' => $code === 0 ? 'success' : 'error',
                'exit_code' => $code,
                'output' => $output,
                'artisan' => Artisan::output(),
            ]);
        }

        if (request()->has('clear_attendance') || request()->has('clear_madan_attendance')) {
            $target = request()->query('user', request()->has('clear_madan_attendance') ? 'madan' : 'all');

            if ($target === 'all') {
                $deleted = \App\Models\TsoAttendance::query()->delete();
                return response()->json([
                    'status' => 'ok',
                    'deleted_all' => $deleted,
                ]);
            }

            $users = \App\Models\User::where('name', 'like', "%{$target}%")
                ->orWhere('email', 'like', "%{$target}%")
                ->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'status' => 'not_found',
                    'message' => "No user matching '{$target}' found",
                    'all_users' => \App\Models\User::select('id', 'name', 'email')->get(),
                ]);
            }

            $userIds = $users->pluck('id')->all();
            $deleted = \App\Models\TsoAttendance::whereIn('user_id', $userIds)->delete();

            return response()->json([
                'status' => 'ok',
                'matched_users' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]),
                'deleted_attendance_records' => $deleted,
            ]);
        }

        if (request()->has('view_attendances')) {
            return response()->json([
                'rows' => \App\Models\TsoAttendance::with('user:id,name,email')->latest()->take(20)->get(),
                'users' => \App\Models\User::select('id', 'name', 'email')->get(),
            ]);
        }

        if (request()->has('view_log')) {
            $logFile = storage_path('logs/laravel.log');
            if (! file_exists($logFile)) {
                return response('No log file found', 200, ['Content-Type' => 'text/plain']);
            }
            $lines = file($logFile);
            return response(implode('', array_slice($lines, -150)), 200, ['Content-Type' => 'text/plain']);
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
