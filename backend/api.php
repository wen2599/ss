<?php
// backend/api.php
require_once 'utils/functions.php';
require_once 'utils/jwt_handler.php';

$action = $_GET['action'] ?? null;

switch ($action) {
    // --- User Authentication ---
    case 'register':
        require 'handlers/user_register.php';
        break;
    
    case 'login':
        require 'handlers/user_login.php';
        break;

    // --- Email Receiving ---
    case 'receive_email':
        require 'handlers/email_receiver.php';
        break;

    // 新增：获取开奖结果
    case 'get_lottery_results':
        require 'handlers/lottery_get_results.php';
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

    default:
        send_json_response(['status' => 'error', 'message' => 'Invalid action specified.'], 404);
        break;
}
