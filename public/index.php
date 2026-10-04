<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$projectPath = realpath(__DIR__.'/../bank-sampah');

// The shared-hosting fallback serves this file from /public_html while the
// Laravel source remains privately stored in a sibling /bank-sampah folder.
// In normal Laravel installations, fall back to the conventional parent path.
if ($projectPath === false || ! is_file($projectPath.'/bootstrap/app.php')) {
    $projectPath = realpath(__DIR__.'/..');
}

if ($projectPath === false || ! is_file($projectPath.'/bootstrap/app.php')) {
    http_response_code(500);
    exit('Laravel application files could not be resolved.');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $projectPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $projectPath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $projectPath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
