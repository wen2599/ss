<?php
declare(strict_types=1);

// --- Environment Variable Loading ---
(function() {
    // Prevent this from running more than once
    if (defined('ENV_LOADED') && ENV_LOADED) {
        return;
    }

    $envPath = __DIR__ . '/../.env'; // Look for .env in the parent directory (server root)
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, '"');

            if (!empty($name)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    define('ENV_LOADED', true);
})();

// --- AGGRESSIVE CORS FIX for shared hosting ---
if (isset($_SERVER['REQUEST_METHOD'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://ss.wenxiuxiu.eu.org',
        'http://localhost:5173'
    ];
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

// --- Global Error Handling ---
set_exception_handler(function (Throwable $e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred on the server.']);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $lastError = error_get_last();
    if ($lastError && ($lastError['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode(['status' => 'error', 'message' => 'A fatal error occurred on the server.']);
    }
});

// --- Database Connection & Migration ---
function getDbConnection(): PDO {
    static $conn = null;
    if ($conn === null) {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbname = $_ENV['DB_DATABASE'] ?? 'email_viewer';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        if (empty($dbname) || empty($username)) {
            error_log('Database credentials (DB_DATABASE or DB_USERNAME) are not set in the .env file.');
            die("Server configuration error: Database credentials missing.");
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
            die("Database connection unavailable: " . $e->getMessage());
        }
    }
    return $conn;
}

// Migrations are handled by the setup.php script and should not be run on every request.
