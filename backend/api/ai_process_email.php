<?php

declare(strict_types=1);

// backend/api/ai_process_email.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../ai_helpers.php';

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '仅允许POST方法']);
    exit;
}

$user_id = verify_jwt_token();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '认证失败，请重新登录。']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email_id = filter_var($data['email_id'] ?? null, FILTER_VALIDATE_INT);
$correction_feedback = trim($data['correction'] ?? ''); // 接收修正指令，并清理空白符

if (!$email_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '请求参数错误：缺少邮件ID。']);
    exit;
}

global $db_connection;
$stmt = $db_connection->prepare("SELECT body FROM emails WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $email_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$email = $result->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => '未找到该邮件或您无权访问。' . ($email_id ? " 邮件ID: {$email_id}" : '')]);
    exit;
}
$stmt->close();

$email_body = $email['body'];

// 将修正指令传递给AI
$organized_data = organize_email_with_ai($email_body, $correction_feedback);

if (!$organized_data) {
    http_response_code(500);
    $error_msg = $correction_feedback 
        ? 'AI根据您的指令修正失败。请尝试更清晰的描述，例如：“第一条是特码，不是平码”。' 
        : 'AI处理邮件失败，请检查AI服务配置（Cloudflare / Gemini API Key）。';
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'AI成功生成或修正结算单。', 'data' => $organized_data]);
