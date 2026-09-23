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

        if (request()->has('check_map')) {
            $key = \App\Support\MapConfig::apiKey();
            $enabled = \App\Support\MapConfig::enabled();
            $centre = \App\Support\MapConfig::defaultCentre();
            $retailersTotal = \Illuminate\Support\Facades\DB::table('retailers')->count();
            $retailersMapped = \Illuminate\Support\Facades\DB::table('retailers')->whereNotNull('latitude')->whereNotNull('longitude')->count();
            return response()->json([
                'key_length' => strlen($key),
                'key_masked' => $key !== '' ? substr($key, 0, 8) . '...' . substr($key, -4) : '',
                'enabled' => $enabled,
                'centre' => $centre,
                'retailers_total' => $retailersTotal,
                'retailers_mapped' => $retailersMapped,
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
