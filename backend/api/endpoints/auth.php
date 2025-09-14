<?php
/**
 * Handles authentication-related endpoints:
 * - /register
 * - /login
 * - /logout
 * - /check_session
 */
switch ($endpoint) {
    case 'register':
        if ($request_method !== 'POST') {
            Response::send_json_error(405, 'Method Not Allowed');
            break;
        }
        $phone = $data['phone'] ?? null;
        $password = $data['password'] ?? null;
        if (!$phone || !$password) Response::send_json_error(400, '手机号和密码不能为空');
        if (strlen($password) < 6) Response::send_json_error(400, '密码长度不能少于6位');

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() > 0) {
            Response::send_json_error(409, '该手机号已被注册');
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $display_id = null;
        do {
            // Use a cryptographically secure random number generator
            $display_id = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE display_id = ?");
            $stmt->execute([$display_id]);
        } while ($stmt->fetchColumn() > 0);

        $default_points = 1000;
        $stmt = $db->prepare("INSERT INTO users (display_id, phone_number, password_hash, points) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$display_id, $phone, $password_hash, $default_points])) {
            Response::send_json(['success' => true, 'message' => '注册成功', 'displayId' => $display_id]);
        } else {
            Response::send_json_error(500, '注册失败，请稍后再试');
        }
        break;

    case 'login':
        if ($request_method !== 'POST') {
            Response::send_json_error(405, 'Method Not Allowed');
            break;
        }
        $phone = $data['phone'] ?? null;
        $password = $data['password'] ?? null;
        if (!$phone || !$password) Response::send_json_error(400, '手机号和密码不能为空');

        $stmt = $db->prepare("SELECT id, display_id, password_hash, points FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['display_id'] = $user['display_id'];
            $_SESSION['points'] = $user['points'];
            Response::send_json(['success' => true, 'user' => ['id' => $user['id'], 'displayId' => $user['display_id'], 'points' => $user['points']]]);
        } else {
            Response::send_json_error(401, '手机号或密码错误');
        }
        break;

    case 'logout':
        if ($request_method !== 'POST') {
            Response::send_json_error(405, 'Method Not Allowed');
            break;
        }
        session_destroy();
        Response::send_json(['success' => true, 'message' => '已成功登出']);
        break;

    case 'check_session':
        if ($request_method !== 'GET') {
            Response::send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (isset($_SESSION['user_id'])) {
            Response::send_json(['success' => true, 'isAuthenticated' => true, 'user' => ['id' => $_SESSION['user_id'], 'displayId' => $_SESSION['display_id'], 'points' => $_SESSION['points']]]);
        } else {
            Response::send_json(['success' => true, 'isAuthenticated' => false]);
        }
        break;
}
