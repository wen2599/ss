<?php
// --- Ultimate Session Handling for Difficult Hosting Environments ---

// Use PHP's built-in function to get a guaranteed-writable temporary directory.
$temp_dir = sys_get_temp_dir();
// Create a unique, application-specific subdirectory within the temp directory.
$session_path = $temp_dir . '/php_sessions_wenge95222';

// Ensure the directory exists. This is a final safeguard.
if (!is_dir($session_path)) {
    // Attempt to create it. This has a very high chance of success in a temp dir.
    @mkdir($session_path, 0755, true);
}

// Set the session save path *before* starting the session.
// This is the most robust way to ensure session handling works.
session_save_path($session_path);


// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Standard Headers & Handlers ---

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Worker-Secret");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

function json_response($data, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

set_exception_handler(function(Throwable $e) {
    // For debugging, you might want to log the error message
    // error_log($e->getMessage());
    json_response(['error' => 'An internal server error occurred.'], 500);
});

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

ini_set('display_errors', '0');
error_reporting(E_ALL);

function load_env($path) {
    if (!file_exists($path)) { throw new Exception(".env file not found at " . $path); }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

load_env(__DIR__ . '/.env');

$pdo = null;
try {
    $host = $_ENV['DB_HOST'] ?? null;
    $dbname = $_ENV['DB_NAME'] ?? null;
    $user = $_ENV['DB_USER'] ?? null;
    $pass = $_ENV['DB_PASS'] ?? '';
    if (!$host || !$dbname || !$user) { throw new Exception("Database configuration is incomplete."); }
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    throw $e;
}
?>