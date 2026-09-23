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

                $t0 = microtime(true);
                $totalRecords = \Illuminate\Support\Facades\DB::table('sales_activation_records')->count();
                $t1 = microtime(true);
                
                $columns = $wod->modelColumns(null, null);
                $t2 = microtime(true);

                $rows = $wod->rdWise(null, null, $columns['models'], $columns['hasOther'], 50);
                $t3 = microtime(true);

                $summary = $wod->summary(null, null);
                $t4 = microtime(true);

                $distributors = $filter->distributors();
                $t5 = microtime(true);

                $models = $filter->models();
                $t6 = microtime(true);

                $view = view('livewire.wod-coverage', [
                    'type' => 'rd',
                    'rdCode' => null,
                    'model' => null,
                    'lifecycle' => '',
                    'rows' => $rows,
                    'columns' => $columns,
                    'summary' => $summary,
                    'types' => \App\Livewire\WodCoverage::TYPES,
                    'rdOptions' => $distributors,
                    'modelOptions' => $models,
                    'labelHeaders' => ['RD Code', 'RD Name'],
                ]);
                $html = $view->render();
                $t7 = microtime(true);

                $simReq = \Illuminate\Http\Request::create('/wod-coverage', 'GET');
                $simReq->setLaravelSession(session());
                $routeRes = app()->handle($simReq);

                $out = [
                    'total_records' => $totalRecords,
                    'total_time' => round($t7 - $t0, 3).'s',
                    'html_length' => strlen($html),
                    'full_route_status' => $routeRes->getStatusCode(),
                ];

                return response()->json($out, 200);
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ], 500);
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
