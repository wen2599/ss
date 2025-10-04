<?php
// --- Start of Inlined Initialization Logic ---

// --- Session Handling ---
$temp_dir = sys_get_temp_dir();
$session_path = $temp_dir . '/php_sessions_wenge95222';
if (!is_dir($session_path)) {
    @mkdir($session_path, 0755, true);
}
session_save_path($session_path);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Environment Variable Loading ---
function load_env($path) {
    if (!file_exists($path)) { return; } // Silently fail if .env is not found
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
load_env(__DIR__ . '/.env');

// --- CORS and Standard Headers ---
$allowed_origin = $_ENV['FRONTEND_URL'] ?? '*';
header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Worker-Secret");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// --- JSON Response Helper ---
function json_response($data, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

// --- Error and Exception Handling ---
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_exception_handler(function(Throwable $e) {
    // In a real app, you would log the error: error_log($e->getMessage());
    json_response(['error' => 'An internal server error occurred.'], 500);
});
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Database Connection ---
$pdo = null;
try {
    $host = $_ENV['DB_HOST'] ?? null;
    $dbname = $_ENV['DB_NAME'] ?? null;
    $user = $_ENV['DB_USER'] ?? null;
    $pass = $_ENV['DB_PASS'] ?? '';
    if ($host && $dbname && $user) {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (PDOException $e) {
    // If the database connection fails, the app can still run.
    // Actions that require the DB will fail gracefully if they check for $pdo.
}

// --- End of Inlined Initialization Logic ---


// --- Main Application Logic ---

// Security check
$worker_secret = $_ENV['WORKER_SECRET'] ?? null;
if (!$worker_secret) {
    json_response(['error' => 'Application is not configured. WORKER_SECRET is missing.'], 500);
}

$request_secret_header = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$request_secret_param = $_GET['worker_secret'] ?? '';
$request_secret_post = $_POST['worker_secret'] ?? '';

$worker_actions = ['email_upload', 'process_email', 'is_user_registered'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$is_authorized = false;
if ($request_secret_header === $worker_secret) {
    $is_authorized = true;
} elseif (in_array($action, $worker_actions) && ($request_secret_param === $worker_secret || $request_secret_post === $worker_secret)) {
    $is_authorized = true;
}

if (!$is_authorized) {
    json_response(['error' => 'Unauthorized access.'], 403);
}

// Action routing
$allowed_actions = [
    'register', 'login', 'logout', 'check_session',
    'process_email', 'is_user_registered', 'email_upload',
];

if (!in_array($action, $allowed_actions)) {
    json_response(['error' => 'Invalid action specified.'], 400);
}

$action_file = __DIR__ . '/actions/' . $action . '.php';

if (file_exists($action_file)) {
    // The $pdo variable is available to the included action file.
    require_once $action_file;
} else {
    json_response(['error' => "Action handler '{$action}.php' not found."], 404);
}
?>