<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 1. Include Dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/BetCalculator.php'; // New dependency

// 2. Set Headers
header('Content-Type: application/json');

// 3. Security & Input Validation
if (!isset($_POST['worker_secret']) || $_POST['worker_secret'] !== $worker_secret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit();
}
if (!isset($_POST['user_email']) || !filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing user_email.']);
    exit();
}
if (!isset($_FILES['chat_file']) || $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File upload error.']);
    exit();
}

$user_email = $_POST['user_email'];
$file_tmp_path = $_FILES['chat_file']['tmp_name'];

// 4. Read file content
$raw_content = file_get_contents($file_tmp_path);
if ($raw_content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded file.']);
    exit();
}

// 5. Database Connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("API DB connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit();
}

// 6. Get User ID from Email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404); // Not Found
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

// 7. Process Betting Slip
$calculation_result = BetCalculator::calculate($raw_content);

$status = 'unrecognized';
$settlement_details = null;
$total_cost = null;

if ($calculation_result !== null) {
    $status = 'processed';
    $settlement_details = json_encode($calculation_result['breakdown'], JSON_UNESCAPED_UNICODE);
    $total_cost = $calculation_result['total_cost'];
}

// 8. Insert into Bills Table
try {
    $sql = "INSERT INTO bills (user_id, raw_content, settlement_details, total_cost, status)
            VALUES (:user_id, :raw_content, :settlement_details, :total_cost, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':raw_content' => $raw_content,
        ':settlement_details' => $settlement_details,
        ':total_cost' => $total_cost,
        ':status' => $status
    ]);

    http_response_code(201); // Created
    echo json_encode([
        'success' => true,
        'message' => 'Bill processed and saved successfully.',
        'status' => $status
    ]);

} catch (PDOException $e) {
    error_log("Bill insertion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the bill to the database.']);
    exit();
}
?>
