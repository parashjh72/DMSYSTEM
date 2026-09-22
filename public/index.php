<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (isset($_GET['info'])) {
    header('Content-Type: text/plain');
    echo "URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
    try {
        require __DIR__.'/../vendor/autoload.php';
        /** @var Application $app */
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->bootstrap();
        echo "Laravel Bootstrapped successfully!\n";
        echo "APP_KEY: " . (config('app.key') ? 'set' : 'NOT SET') . "\n";
        echo "DB_CONNECTION: " . config('database.default') . "\n";
        echo "DB_DATABASE: " . config('database.connections.' . config('database.default') . '.database') . "\n";
        echo "DB_HOST: " . config('database.connections.' . config('database.default') . '.host') . "\n";
        echo "Check .env in app root: " . (file_exists(__DIR__.'/../.env') ? 'exists' : 'does not exist') . "\n";
        echo "Check .env in parent: " . (file_exists(__DIR__.'/../../.env') ? 'exists' : 'does not exist') . "\n";
        if (!file_exists(__DIR__.'/../.env') && file_exists(__DIR__.'/../../.env')) {
            $copied = copy(__DIR__.'/../../.env', __DIR__.'/../.env');
            echo "Copied .env from parent: " . ($copied ? 'SUCCESS' : 'FAILED') . "\n";
        }
        if (file_exists(__DIR__.'/../.env')) {
            $envLines = file(__DIR__.'/../.env');
            foreach ($envLines as $line) {
                if (preg_match('/^(APP_KEY|APP_NAME|APP_ENV|DB_CONNECTION|DB_DATABASE|DB_USERNAME|DB_HOST)=/', $line)) {
                    echo "Env entry: " . trim($line) . "\n";
                }
            }
        }
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        echo "DB: Successfully connected to " . \Illuminate\Support\Facades\DB::connection()->getDatabaseName() . "\n";
    } catch (\Throwable $e) {
        echo "Exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
    }
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
