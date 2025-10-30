<?php
// 文件名: get_details.php
// 路径: backend/api/emails/get_details.php
ini_set('display_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/helpers.php';

$user = get_auth_user();
if (!$user) {
    json_response(['message' => 'Unauthorized'], 401);
}

$email_id = $_GET['id'] ?? null;
if (!$email_id) {
    json_response(['message' => 'Email ID is required.'], 400);
}

try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM received_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $user->user_id]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($email) {
        // 解码 JSON 字段，如果它们是 null，则返回空数组或对象
        $email['structured_data'] = $email['structured_data'] ? json_decode($email['structured_data'], true) : [];
        $email['settlement_result'] = $email['settlement_result'] ? json_decode($email['settlement_result'], true) : null;
        json_response($email, 200);
    } else {
        json_response(['message' => 'Email not found or access denied.'], 404);
    }

} catch (PDOException $e) {
    error_log("Email Detail API Error: " . $e->getMessage());
    json_response(['message' => 'Database error.'], 500);
}