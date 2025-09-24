<?php
// Action: Handle raw email part upload from the Cloudflare worker

require_once __DIR__ . '/../lib/BetCalculator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

if (!isset($_POST['worker_secret']) || $_POST['worker_secret'] !== $worker_secret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit();
}

// Validate required fields
if (!isset($_POST['user_email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: user_email']);
    exit();
}
if (!isset($_POST['charset'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: charset']);
    exit();
}
if (!isset($_FILES['email_part_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required file: email_part_file']);
    exit();
}
if ($_FILES['email_part_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File upload error from worker.']);
    exit();
}

$user_email = $_POST['user_email'];
$charset = $_POST['charset'];
$file_tmp_path = $_FILES['email_part_file']['tmp_name'];

// 1. Read the raw bytes from the uploaded file part
$raw_body_bytes = file_get_contents($file_tmp_path);
if ($raw_body_bytes === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded email part file.']);
    exit();
}

// 2. Convert the character encoding of the raw bytes to UTF-8
// This is the most reliable step, as PHP has strong tools for this.
$raw_content = mb_convert_encoding($raw_body_bytes, 'UTF-8', $charset);

// 3. Find user
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

// 4. Process content with BetCalculator
$settlement_slip = BetCalculator::calculate($raw_content);

$status = 'unrecognized';
$settlement_details = null;
$total_cost = null;

if ($settlement_slip !== null) {
    $status = 'processed';
    $settlement_details = json_encode($settlement_slip, JSON_UNESCAPED_UNICODE);
    $total_cost = $settlement_slip['summary']['total_cost'];
}

// 5. Save the bill to the database
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

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Bill processed and saved successfully.',
        'status' => $status
    ]);

} catch (PDOException $e) {
    error_log("Bill insertion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the bill to the database.']);
}
?>