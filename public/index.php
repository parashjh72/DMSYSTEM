<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (isset($_GET['info'])) {
    header('Content-Type: text/plain');
    echo "URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
    echo "SCRIPT: " . ($_SERVER['SCRIPT_NAME'] ?? '') . "\n";
    try {
        require __DIR__.'/../vendor/autoload.php';
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $request = Request::capture();
        echo "Laravel Request URI: " . $request->getRequestUri() . "\n";
        echo "Laravel PathInfo: " . $request->getPathInfo() . "\n";
        echo "APP_KEY: " . (config('app.key') ? 'set' : 'NOT SET') . "\n";
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        echo "DB: Connected to " . \Illuminate\Support\Facades\DB::connection()->getDatabaseName() . "\n";
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
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
