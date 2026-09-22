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
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        echo "DB: Successfully connected to " . \Illuminate\Support\Facades\DB::connection()->getDatabaseName() . "\n";
    } catch (\Throwable $e) {
        echo "Exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
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
