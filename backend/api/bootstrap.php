<?php
declare(strict_types=1);

// --- Production-Grade Error Handling ---
// Prevent displaying errors to the user, and ensure all errors are logged.
// This is the first thing to run to catch any and all errors during startup.
error_reporting(E_ALL);
ini_set('display_errors', '1'); // Changed from '0' to '1' for debugging
ini_set('log_errors', '1');

// --- Environment Variable Loading ---
(function() {
    if (defined('ENV_LOADED') && ENV_LOADED) {
        return;
    }
    
    // Add the production server path to the list of possible .env locations.
    $possiblePaths = [
        __DIR__ . '/../.env',       // Path for local dev if backend is document root
        __DIR__ . '/../../.env',    // Path for local dev if project root is document root
        __DIR__ . '/.env',          // Path if .env is inside the api folder
    ];

    $foundEnvPath = null;
    foreach ($possiblePaths as $path) {
        $handle = @fopen($path, 'r');
        if ($handle !== false) {
            fclose($handle);
            $foundEnvPath = $path;
            break;
        }
    }

    if ($foundEnvPath) {
        $lines = file($foundEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, '"');
            if (!empty($name)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    } else {
        error_log("CRITICAL: .env file not found in any expected location.");
    }
    define('ENV_LOADED', true);
})();

// --- Session Configuration ---
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Aggressive CORS Headers (FORCED FOR DEBUGGING) ---
if (isset($_SERVER['REQUEST_METHOD'])) {
    // Force allow all origins for debugging. In production, use specific origins.
    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    // Ensure all necessary headers are allowed
    header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-Worker-Secret, Accept, Origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// --- Database Connection ---
function get_db_connection(): \PDO {
    static $conn = null;
    if ($conn === null) {
        if (!class_exists('PDO')) {
            // If PDO is missing, we can't use the nice JSON error handler yet, so die with a plain message.
            http_response_code(503);
            error_log('FATAL: PDO extension is not installed or enabled.');
            exit(json_encode(['status' => 'error', 'message' => 'Service Unavailable: A required server extension (PDO) is missing.']));
        }

        $host = $_ENV['DB_HOST'] ?? null;
        $port = (int)($_ENV['DB_PORT'] ?? '3306');
        $dbname = $_ENV['DB_DATABASE'] ?? null;
        $username = $_ENV['DB_USER'] ?? null;
        $password = $_ENV['DB_PASSWORD'] ?? null;

        if (!$host || !$dbname || !$username) {
            // Can't use send_json_error yet if it's not defined.
            http_response_code(503);
            error_log('CRITICAL: Database environment variables are not configured.');
            exit(json_encode(['status' => 'error', 'message' => 'Service Unavailable: Server database is not configured correctly.']));
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $conn = new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // Can't use send_json_error yet, keep it simple.
            http_response_code(503);
            exit(json_encode([
                'status' => 'error',
                'message' => 'Service Unavailable: Could not connect to the database.'
            ]));
        }
    }
    return $conn;
}

// --- Unified JSON Error Response Function ---
function send_json_error(int $statusCode, string $message, ?Throwable $e = null): void {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
    }
    $response = ['status' => 'error', 'message' => $message];
    // This condition now uses $_ENV['APP_DEBUG'] directly
    if ($e && ($_ENV['APP_DEBUG'] ?? 'false') === 'true') { 
        $response['details'] = $e->getMessage();
        $response['file'] = $e->getFile();
        $response['line'] = $e->getLine();
        $response['trace'] = explode("\n", $e->getTraceAsString());
    }
    echo json_encode($response);
    exit;
}

// --- Global Error & Exception Handling ---
set_exception_handler(function (Throwable $e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . " Request: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
    send_json_error(500, 'An unexpected server error occurred.', $e);
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $lastError = error_get_last();
    if ($lastError && ($lastError['type'] & (E_ERROR | E_PARSE))) {
        // Use a separate, simpler error log here to avoid circular dependencies if send_json_error fails
        error_log("Fatal Error (Shutdown): {$lastError['message']} in {$lastError['file']}:{$lastError['line']} Request: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        // Don't call send_json_error here as the response stream might be broken
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode(['status' => 'error', 'message' => 'A fatal server error occurred.']);
    }
});
