<?php
require_once __DIR__ . '/../lib/BetCalculator.php';
require_once __DIR__ . '/../lib/utils.php';

// 认证及参数检查
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
if (!isset($_POST['user_email']) || !isset($_FILES['raw_email_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields from worker.']);
    exit();
}
if ($_FILES['raw_email_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File upload error from worker.']);
    exit();
}

$user_email = $_POST['user_email'];
$file_tmp_path = $_FILES['raw_email_file']['tmp_name'];
$raw_email_content = file_get_contents($file_tmp_path);
if ($raw_email_content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read uploaded email file.']);
    exit();
}
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
    exit();
}
$user_id = $user['id'];

// 邮件正文处理
$detected_charset = null;
$text_body = get_plain_text_body_from_email($raw_email_content, $detected_charset);
$text_body = smart_convert_encoding($text_body, $detected_charset);

// HTML正文也智能转码
$html_body = null;
if (isset($_FILES['html_body']) && $_FILES['html_body']['error'] === UPLOAD_ERR_OK) {
    $html_raw = file_get_contents($_FILES['html_body']['tmp_name']);
    $html_body = smart_convert_encoding($html_raw, $detected_charset);
} elseif (!empty($_POST['html_body'])) {
    $html_body = smart_convert_encoding($_POST['html_body'], $detected_charset);
}

$attachments_meta = handle_attachments($user_id);

// 确定用于计算的最终内容
$calculation_content = trim($text_body);

// 如果纯文本内容为空，则尝试从HTML转换
if (empty($calculation_content) && !empty($html_body)) {
    // Convert basic block tags to newlines for better structure, then strip all tags.
    $processed_html = preg_replace(['/<br\s*\/?>/i', '/<\/p>/i', '/<div/i'], ["\n", "\n\n", "\n<div"], $html_body);
    $calculation_content = trim(strip_tags($processed_html));
    $calculation_content = html_entity_decode($calculation_content, ENT_QUOTES, 'UTF-8');
}

// 如果内容仍然为空，则回退到原始邮件内容（不太可能，但作为保障）
if (empty($calculation_content)) {
    $calculation_content = smart_convert_encoding($raw_email_content, $detected_charset);
}

// 多段结算
$multi_slip = BetCalculator::calculateMulti($calculation_content);
$status = 'unrecognized';
$settlement_details = null;
$total_cost = null;

// Only set status to 'processed' if the calculator found valid bet slips.
if ($multi_slip !== null && !empty($multi_slip['slips'])) {
    $status = 'processed';
    $settlement_details = json_encode($multi_slip, JSON_UNESCAPED_UNICODE);
    $total_cost = $multi_slip['summary']['total_cost'] ?? 0;
}

try {
    $sql = "INSERT INTO bills (user_id, raw_content, settlement_details, total_cost, status)
            VALUES (:user_id, :raw_content, :settlement_details, :total_cost, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':raw_content' => $text_body . ($html_body ? "\n\n---HTML正文---\n" . $html_body : ''),
        ':settlement_details' => $settlement_details,
        ':total_cost' => $total_cost,
        ':status' => $status
    ]);
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Bill processed and saved successfully.',
        'status' => $status,
        'attachments' => $attachments_meta
    ]);
} catch (PDOException $e) {
    error_log("Bill insertion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save the bill to the database.']);
}
?>
