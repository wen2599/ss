<?php
// init.php
// This is the global initialization file for the entire backend application.
// It should be included at the very beginning of any entry-point script.

// --- 1. Setup Error Reporting ---
// Report all errors during development. In a production environment,
// you might want to log errors to a file instead of displaying them.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 2. Load Composer Autoloader ---
require_once __DIR__ . '/vendor/autoload.php';

// --- 3. Load Environment Variables ---
// Centralized loading of .env file.
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    // This is a critical failure, as the app cannot be configured.
    error_log("CRITICAL: Could not load .env file. " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Server configuration error.']);
    exit();
}

// --- 4. Set Global Exception Handler ---
// This will catch any uncaught exceptions and return a clean JSON error.
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    http_response_code(500);
    // Ensure the content type is JSON, as this handler can be triggered at any time.
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected server error occurred.'
    ]);
    exit;
});


// --- 5. Configure CORS (Cross-Origin Resource Sharing) ---
// This allows the frontend (running on a different port/domain) to talk to the backend.
$allowed_origins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:5173');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Worker-Secret');
}
// Handle pre-flight requests for CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit();
}

// --- 6. Set Default Header ---
// All responses from this point should be JSON.
header('Content-Type: application/json');


// --- 7. Database Connection ---
// The global $pdo object is created here and will be available to all scripts.
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'lottery_app';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';
$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // This will be caught by our global exception handler, so no need to echo here.
    throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
}


// --- 8. Start Session ---
// Must be started after all other headers and setup.
session_start();

// --- 9. Setup Global Logger ---
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logLevel = Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO');
$log = new Logger('app');
$log->pushHandler(new StreamHandler(__DIR__ . '/app.log', $logLevel));


// --- 10. Define Global Variables (if any) ---
// For example, making the admin ID available globally.
$admin_id = $_ENV['TELEGRAM_ADMIN_ID'] ?? null;
?>