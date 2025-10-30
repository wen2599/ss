<?php
// 文件名: get_list.php
// 路径: backend/api/emails/get_list.php
ini_set('display_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/helpers.php';

$user = get_auth_user();
if (!$user) {
    json_response(['message' => 'Unauthorized'], 401);
}

try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id, subject, from_address, status, created_at FROM received_emails WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user->user_id]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response($emails, 200);

} catch (PDOException $e) {
    error_log("Email List API Error: " . $e->getMessage());
    json_response(['message' => 'Database error.'], 500);
}