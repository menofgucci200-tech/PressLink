<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode... (storage/ commun à
// toutes les versions en production : voir .presslink-shared dans bootstrap/app.php)
$storage = is_file($sharedFile = __DIR__.'/../.presslink-shared')
    ? rtrim(trim((string) file_get_contents($sharedFile)), '/').'/storage'
    : __DIR__.'/../storage';
if (file_exists($maintenance = $storage.'/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
