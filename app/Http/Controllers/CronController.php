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

        if (request()->has('test_wod')) {
            try {
                $user = \App\Models\User::first();
                auth()->login($user);
                $wod = app(\App\Services\Reporting\WodCoverageService::class);
                $filter = app(\App\Services\Reporting\FilterOptions::class);
                $c = new \App\Livewire\WodCoverage();
                $c->mount();
                $v = $c->render($wod, $filter);
                $html = $v->render();
                return response("WOD Render Success! Length: " . strlen($html), 200);
            } catch (\Throwable $e) {
                return response("WOD Error: " . get_class($e) . ": " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString(), 500);
            }
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
