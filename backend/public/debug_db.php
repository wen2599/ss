<?php

// Enable full error reporting for debugging purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Debugger</h1>";

$dotenvPath = __DIR__ . '/../.env';
echo "<p>Looking for .env file at: <code>" . realpath(dirname($dotenvPath)) . '/' . basename($dotenvPath) . "</code></p>";

if (!file_exists($dotenvPath)) {
    die("<p style='color:red;'><b>Error:</b> .env file not found at the specified path.</p>");
}

if (!is_readable($dotenvPath)) {
    die("<p style='color:red;'><b>Error:</b> .env file exists but is not readable. Please check file permissions.</p>");
}

// Manually include the DotEnv class
require_once __DIR__ . '/../src/core/DotEnv.php';

try {
    // Load variables directly from the .env file
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
    echo "<p>Successfully loaded .env file.</p>";
    echo "<h2>Loaded Variables:</h2>";
    echo "<pre>";
    // Mask the password for security
    $safeEnv = $env;
    if (isset($safeEnv['DB_PASSWORD'])) {
        $safeEnv['DB_PASSWORD'] = '********';
    }
    if (isset($safeEnv['TELEGRAM_BOT_TOKEN'])) {
        $safeEnv['TELEGRAM_BOT_TOKEN'] = '********';
    }
     if (isset($safeEnv['TELEGRAM_WEBHOOK_SECRET'])) {
        $safeEnv['TELEGRAM_WEBHOOK_SECRET'] = '********';
    }
    print_r($safeEnv);
    echo "</pre>";

    // Extract database credentials
    $servername = $env['DB_HOST'] ?? null;
    $username = $env['DB_USER'] ?? null;
    $password = $env['DB_PASSWORD'] ?? null;
    $dbname = $env['DB_DATABASE'] ?? null;
    $port = $env['DB_PORT'] ?? 3306;

    if (!$servername || !$username || !$dbname) {
         die("<p style='color:red;'><b>Error:</b> One or more required database variables (DB_HOST, DB_USER, DB_DATABASE) are missing from the .env file.</p>");
    }

    echo "<h2>Attempting Connection...</h2>";
    echo "<p>Host: " . htmlspecialchars($servername) . "</p>";
    echo "<p>Database: " . htmlspecialchars($dbname) . "</p>";
    echo "<p>User: " . htmlspecialchars($username) . "</p>";
    echo "<p>Port: " . htmlspecialchars($port) . "</p>";

    // Establish the connection
    $conn = new mysqli($servername, $username, $password, $dbname, (int)$port);

    // Check the connection
    if ($conn->connect_error) {
        die("<p style='color:red;'><b>Connection Failed:</b> " . htmlspecialchars($conn->connect_error) . "</p>");
    }

    echo "<p style='color:green;'><b>Success!</b> Database connection was successful.</p>";

    $conn->close();

} catch (Exception $e) {
    echo "<p style='color:red;'><b>An unexpected PHP error occurred:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
}