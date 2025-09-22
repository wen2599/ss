<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

// 1. Include Dependencies
require_once __DIR__ . '/config.php';

// 2. Set Headers
header('Content-Type: application/json');

// 3. Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'You must be logged in to view bills.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 4. Database Connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Get Bills DB connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit();
}

// 5. Fetch Bills from Database
try {
    $sql = "SELECT id, total_cost, status, created_at FROM bills WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);

    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(['success' => true, 'bills' => $bills]);

} catch (PDOException $e) {
    error_log("Get Bills DB query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve bills.']);
    exit();
}
?>
