<?php
// backend/bootstrap.php

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

// Define a project root constant for consistent path resolution.
define('PROJECT_ROOT', dirname(__DIR__));

// 1. Load Composer's autoloader
require_once PROJECT_ROOT . '/vendor/autoload.php';

// 2. Load Environment Variables
// Ensure Dotenv is loaded. It expects the .env file in the PROJECT_ROOT.
if (file_exists(PROJECT_ROOT . '/.env')) {
    $dotenv = Dotenv::createImmutable(PROJECT_ROOT);
    $dotenv->load();
} else {
    // A fallback or error for when the .env file is missing.
    // In a production environment, you might handle this differently.
    error_log("CRITICAL: .env file not found at " . PROJECT_ROOT);
    // We can exit here or rely on getenv() to return false for missing keys,
    // which our application logic should handle gracefully.
}

// 3. Eloquent Database ORM Setup
$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => getenv('DB_CONNECTION') ?: 'mysql',
    'host'      => getenv('DB_HOST') ?: '127.0.0.1',
    'port'      => getenv('DB_PORT') ?: '3306',
    'database'  => getenv('DB_DATABASE'),
    'username'  => getenv('DB_USERNAME'),
    'password'  => getenv('DB_PASSWORD'),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

// Make this Capsule instance available globally via static methods.
$capsule->setAsGlobal();

// Boot Eloquent.
$capsule->boot();
