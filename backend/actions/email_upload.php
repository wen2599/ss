<?php
// Action: Handle parsed email data from the Cloudflare worker
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

// Validate required fields from the worker
$required_fields = ['user_email', 'encoded_body', 'transfer_encoding', 'charset'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: {$field}"]);
        exit();
    }
}

$user_email = $_POST['user_email'];
$encoded_body = $_POST['encoded_body'];
$transfer_encoding = strtolower($_POST['transfer_encoding']);
$charset = $_POST['charset'];
$decoded_body = '';

// 1. Decode the transfer encoding (e.g., base64, quoted-printable)
switch ($transfer_encoding) {
    case 'base64':
        $decoded_body = base64_decode($encoded_body);
        break;
    case 'quoted-printable':
        $decoded_body = quoted_printable_decode($encoded_body);
        break;
    default: // 7bit, 8bit, binary
        $decoded_body = $encoded_body;
        break;
}

if ($decoded_body === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to decode email body based on transfer encoding.']);
    exit();
}

// 2. Convert character encoding to UTF-8
// Use a broad list of possible source encodings, with the one from the email first.
$detection_order = [$charset, 'UTF-8', 'GBK', 'GB2312', 'BIG5', 'ISO-8859-1'];
$raw_content = mb_convert_encoding($decoded_body, 'UTF-8', $detection_order);

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