<?php
declare(strict_types=1);

// --- Environment Variable Loading ---
(function() {
    if (defined('ENV_LOADED') && ENV_LOADED) {
        return;
    }
    
    // Attempt to locate .env file based on current execution context
    // Test common paths relative to bootstrap.php

    $possiblePaths = [
        __DIR__ . '/../.env',      // .env in the "server root" (public_html) if api is in public_html/api
        __DIR__ . '/../../.env',   // .env in the directory above "server root" if api is in public_html/api
        __DIR__ . '/.env',         // .env directly in api/ (less likely but possible)
    ];

    $foundEnvPath = null;
    foreach ($possiblePaths as $path) {
        error_log("Trying .env path: " . $path); // Debug output
        if (file_exists($path)) {
            $foundEnvPath = $path;
            break;
        }
    }

    if ($foundEnvPath) {
        error_log("Found .env at: " . $foundEnvPath); // Debug output
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
        error_log("ERROR: .env file not found in any expected location."); // Debug output
    }
    define('ENV_LOADED', true);
})();

// --- Session Configuration ---
session_set_cookie_params([
    'lifetime' => 0, // Session cookie lasts until the browser is closed.
    'path' => '/',
    'domain' => '', // Let the browser decide based on the request host. Avoid hardcoding.
    'secure' => true, // Only send over HTTPS.
    'httponly' => true, // Prevent JavaScript access.
    'samesite' => 'None' // Allow cross-origin requests.
]);

// Start the session.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// --- Aggressive CORS Headers ---
if (isset($_SERVER['REQUEST_METHOD'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    $defaultAllowedOrigins = ['https://ss.wenxiuxiu.eu.org', 'http://localhost:5173'];
    $configuredOrigins = $_ENV['ALLOWED_ORIGINS'] ?? null;

    if ($configuredOrigins) {
        $allowedOrigins = array_map('trim', explode(',', $configuredOrigins));
    } else {
        $allowedOrigins = $defaultAllowedOrigins;
    }

    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-Worker-Secret, Accept, Origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// --- Unified JSON Error Response Function ---
function send_json_error(int $statusCode, string $message, ?Throwable $e = null): void {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
    }
    $response = ['status' => 'error', 'message' => $message];
    if ($e && ($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        $response['details'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// --- Global Error & Exception Handling ---
set_exception_handler(function (Throwable $e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    send_json_error(500, 'An unexpected server error occurred.', $e);
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $lastError = error_get_last();
    if ($lastError && ($lastError['type'] & (E_ERROR | E_PARSE))) {
        error_log("Fatal Error: {$lastError['message']} in {$lastError['file']}:{$lastError['line']}");
        send_json_error(500, 'A fatal server error occurred.');
    }
});

// --- Database Connection ---
function getDbConnection(): PDO {
    static $conn = null;
    if ($conn === null) {
        // --- Pre-condition Check: Ensure PDO extension is loaded ---
        if (!class_exists('PDO')) {
            error_log('FATAL: PDO class not found. The pdo_mysql extension is likely missing.');
            send_json_error(503, 'Service Unavailable: A required server extension is missing.');
        }

        $host = $_ENV['DB_HOST'] ?? null;
        $dbname = $_ENV['DB_DATABASE'] ?? null; // Corrected to DB_DATABASE
        $username = $_ENV['DB_USER'] ?? null;
        $password = $_ENV['DB_PASSWORD'] ?? null;

        if (!$host || !$dbname || !$username) {
            error_log('Database configuration (DB_HOST, DB_DATABASE, DB_USER) is incomplete.'); // Corrected message
            send_json_error(503, 'Service Unavailable: Server is not configured correctly.');
        }

        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $conn = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            send_json_error(503, 'Service Unavailable: Could not connect to the database.', $e);
        }
    }
    return $conn;
}
