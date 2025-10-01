<?php
// Centralized entry point for all API requests

// Simple file-based logger for debugging
function write_log($message) {
    $log_file = __DIR__ . '/debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    // Add a separator for new requests to make the log easier to read
    if (strpos($message, '---') !== false) {
        file_put_contents($log_file, "\n" . $message . "\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND);
    }
}

write_log("--- New Request: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown URI') . " ---");

// 1. Common Setup
// NOTE: session_start() must be called before any output.
session_start();

// Dynamically set the origin to support credentials.
// In a production environment, you should replace this with a strict whitelist of allowed origins.
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    // Fallback for requests without an origin header
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Worker-Secret");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

// Security Check: Validate the secret header from the Cloudflare Worker.
// This ensures that requests are coming from the worker and not directly from the public internet.
if (!isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== $worker_secret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Missing or invalid secret.']);
    exit();
}

// 2. Database Connection (passed to action scripts)
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log("DB connection error in router: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit();
}

// 3. Routing
$action = $_GET['action'] ?? '';
$action_file = __DIR__ . '/actions/' . $action . '.php';

if ($action && file_exists($action_file)) {
    // The required file will have access to the $pdo variable.
    require $action_file;
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint not found.']);
}

?>