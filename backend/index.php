<?php
// Centralized entry point for all API requests

require_once __DIR__ . '/lib/utils.php';

write_log("--- New Request: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown URI') . " ---");

// 1. Common Setup
// NOTE: session_start() must be called before any output.
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

// 2. Database Connection (passed to action scripts)
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
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
