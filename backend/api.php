<?php
// backend/api.php
// **注意：这个文件现在被 proxy.php 包含，而不是直接访问**

// config.php 已经在 proxy.php 中加载
require_once 'utils/functions.php';
require_once 'utils/jwt_handler.php';

// ! CORS 和 OPTIONS 请求处理已移除，因为代理会处理 !

// 路由逻辑保持不变
$action = $_GET['action'] ?? null;

switch ($action) {
    // --- User Authentication ---
    case 'register':
        require 'handlers/user_register.php';
        break;
    
    case 'login':
        require 'handlers/user_login.php';
        break;

    // --- Protected Endpoint Example ---
    case 'get_profile':
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            $token = $matches[1];
            $payload = JWTHandler::validate_token($token);
            if ($payload) {
                send_json_response(['status' => 'success', 'message' => 'Welcome user!', 'user_id' => $payload['user_id']]);
            } else {
                send_json_response(['status' => 'error', 'message' => 'Invalid or expired token.'], 401);
            }
        } else {
            send_json_response(['status' => 'error', 'message' => 'Authorization header not found.'], 401);
        }
        break;

    // TODO: 未来在这里添加获取开奖号码、邮件列表等 action

    default:
        send_json_response(['status' => 'error', 'message' => 'Invalid action specified.'], 404);
        break;
}
