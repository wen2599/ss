<?php
// --- Custom Session Handling & Logging ---
$log_file = __DIR__ . '/debug.log';
// Clear the log for each new request to get clean data
file_put_contents($log_file, "--- NEW REQUEST AT " . date('Y-m-d H:i:s') . " ---\n");

try {
    file_put_contents($log_file, "Step 1: init.php script started.\n", FILE_APPEND);

    // Define a dedicated, writable directory for session files.
    $session_path = __DIR__ . '/sessions';
    file_put_contents($log_file, "Step 2: Session path defined as: " . $session_path . "\n", FILE_APPEND);

    // Check if the session directory exists.
    if (!is_dir($session_path)) {
        file_put_contents($log_file, "Step 3: Session directory does not exist. Attempting to create it.\n", FILE_APPEND);
        // The third parameter 'true' allows the creation of nested directories.
        if (!mkdir($session_path, 0755, true) && !is_dir($session_path)) {
            throw new RuntimeException(sprintf('Fatal: Could not create session directory "%s".', $session_path));
        }
        file_put_contents($log_file, "Step 4: Session directory created successfully.\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "Step 3: Session directory already exists.\n", FILE_APPEND);
    }

    // Set the session save path.
    session_save_path($session_path);
    file_put_contents($log_file, "Step 5: Session save path set successfully.\n", FILE_APPEND);

    // Start session
    if (session_status() == PHP_SESSION_NONE) {
        file_put_contents($log_file, "Step 6: Attempting to start session...\n", FILE_APPEND);
        session_start();
        file_put_contents($log_file, "Step 7: session_start() completed.\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "Step 6: Session already active.\n", FILE_APPEND);
    }

} catch (Throwable $e) {
    // If any of the above steps fail, log the error and exit cleanly.
    file_put_contents($log_file, "FATAL ERROR in session handling: " . $e->getMessage() . "\n", FILE_APPEND);
    // Exit with a JSON response even in this critical path
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server configuration error during session initialization.']);
    exit;
}

// --- Standard Headers & Handlers (from here on, things should be safe) ---

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
    global $log_file;
    file_put_contents($log_file, "GLOBAL EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
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
file_put_contents($log_file, "Step 8: .env file loaded.\n", FILE_APPEND);

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
    file_put_contents($log_file, "Step 9: Database connection successful.\n", FILE_APPEND);
} catch (Throwable $e) {
    throw $e;
}
?>