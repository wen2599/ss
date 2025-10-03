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

// --- 1.5. Simple Logger Implementation ---
// A basic logger to standardize logging output.
class SimpleLogger {
    private function log($level, $message, $context = []) {
        // Ensure context is an array
        if (!is_array($context)) {
            $context = [];
        }

        $log_message = sprintf(
            "[%s] [%s] %s %s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        // Use PHP's built-in error_log function to handle the logging.
        // This will send the message to the web server's error log file.
        error_log(trim($log_message));
    }

    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }

    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }

    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
}
$log = new SimpleLogger();

// --- 2. Manual .env file loading ---
// Since composer is not available, we manually parse the .env file.
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^\'(.*)\'$/", $value, $matches)) {
                $value = $matches[1];
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
} else {
    error_log("CRITICAL: Could not find .env file.");
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Server configuration file is missing.']);
    exit();
}


// --- 3. Set Global Exception Handler ---
// This will catch any uncaught exceptions and return a clean JSON error.
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected server error occurred.'
    ]);
    exit;
});


// --- 4. Configure CORS (Cross-Origin Resource Sharing) ---
$allowed_origins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:5173');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Worker-Secret');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit();
}

// --- 5. Set Default Header ---
header('Content-Type: application/json');


// --- 6. Database Connection ---
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
    // Use the global logger to log the critical database connection error.
    $log->error("Database connection failed", ['error' => $e->getMessage()]);
    // Throw a generic exception to be caught by the global exception handler.
    // This prevents leaking sensitive database details in the public error response.
    throw new Exception("Could not connect to the database.");
}


// --- 7. Start Session ---
session_start();

// --- 8. Define Global Variables (if any) ---
$admin_id = $_ENV['TELEGRAM_ADMIN_ID'] ?? null;
?>