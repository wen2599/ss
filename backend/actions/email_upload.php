<?php
// Action: Handle file upload from email worker

// This endpoint is for the email worker, so it uses POST and expects multipart/form-data
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
$raw_content = file_get_contents($file_tmp_path);

if ($raw_content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded file.']);
    exit();
}

// The $pdo variable is inherited from index.php
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

$calculation_result = BetCalculator::calculate($raw_content);

$status = 'unrecognized';
$settlement_details = null;
$total_cost = null;

if ($calculation_result !== null) {
    $status = 'processed';
    $settlement_details = json_encode($calculation_result['breakdown'], JSON_UNESCAPED_UNICODE);
    $total_cost = $calculation_result['total_cost'];
}

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
