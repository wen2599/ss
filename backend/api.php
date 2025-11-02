<?php
// backend/api.php
require_once 'utils/functions.php';
require_once 'utils/jwt_handler.php';
require_once 'config.php'; // 确保PDO实例可用

// --- "Authentication Middleware" ---
// 检查需要保护的action
$protected_actions = [
    'get_profile', 
    'get_user_emails', 
    'get_email_batches', 
    'process_email_segmentation',
    // --- 新增的受保护路由 ---
    'get_user_odds',
    'set_user_odds_by_text'
];

$action = $_GET['action'] ?? null;
$current_user_id = null;

if (in_array($action, $protected_actions)) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $token = $matches[1];
        $payload = JWTHandler::validate_token($token);
        if ($payload && isset($payload['user_id'])) {
            $current_user_id = $payload['user_id'];
        } else {
            send_json_response(['status' => 'error', 'message' => 'Invalid or expired token.'], 401);
            exit;
        }
    } else {
        send_json_response(['status' => 'error', 'message' => 'Authorization header not found.'], 401);
        exit;
    }
}


// --- Routing ---
switch ($action) {
    // --- Public Routes ---
    case 'register':
        require 'handlers/user_register.php';
        break;
    case 'login':
        require 'handlers/user_login.php';
        break;
    case 'get_lottery_results':
        require 'handlers/lottery_get_results.php';
        break;

    // --- Semi-Public Routes (e.g., requires a secret key, not JWT) ---
    case 'receive_email':
        require 'handlers/email_receiver.php';
        break;

    // --- Protected Routes (JWT required) ---
    case 'get_profile':
        // 认证已在上面完成，$current_user_id 已设置
        send_json_response(['status' => 'success', 'user_id' => $current_user_id]);
        break;
    case 'get_user_emails':
        require 'handlers/get_user_emails.php';
        break;
    case 'get_email_content':
        require 'handlers/get_email_content.php';
        break;
    case 'get_email_batches':
        require 'handlers/get_email_batches.php';
        break;
    case 'process_email_segmentation':
        require 'handlers/process_email_segmentation.php';
        break;
    // --- 新增的路由处理 ---
    case 'get_user_odds':
        require 'handlers/get_user_odds.php';
        break;
    case 'set_user_odds_by_text':
        require 'handlers/set_user_odds_by_text.php';
        break;

    default:
        send_json_response(['status' => 'error', 'message' => 'Invalid action specified.'], 404);
        break;
}
