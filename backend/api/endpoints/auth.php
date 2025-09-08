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
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        $phone = $data['phone'] ?? null;
        $password = $data['password'] ?? null;
        if (!$phone || !$password) send_json_error(400, '手机号和密码不能为空');
        if (strlen($password) < 6) send_json_error(400, '密码长度不能少于6位');
        $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) send_json_error(409, '该手机号已被注册');
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $display_id = null;
        do {
            $display_id = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("SELECT id FROM users WHERE display_id = ?");
            $stmt->bind_param('s', $display_id);
            $stmt->execute();
        } while ($stmt->get_result()->num_rows > 0);
        $default_points = 1000;
        $stmt = $db->prepare("INSERT INTO users (display_id, phone_number, password_hash, points) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sssi', $display_id, $phone, $password_hash, $default_points);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '注册成功', 'displayId' => $display_id]);
        } else {
            send_json_error(500, '注册失败，请稍后再试');
        }
        break;

    case 'login':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        $phone = $data['phone'] ?? null;
        $password = $data['password'] ?? null;
        if (!$phone || !$password) send_json_error(400, '手机号和密码不能为空');
        $stmt = $db->prepare("SELECT id, display_id, password_hash, points FROM users WHERE phone_number = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['display_id'] = $user['display_id'];
            $_SESSION['points'] = $user['points'];
            echo json_encode(['success' => true, 'user' => ['id' => $user['id'], 'displayId' => $user['display_id'], 'points' => $user['points']]]);
        } else {
            send_json_error(401, '手机号或密码错误');
        }
        break;

    case 'logout':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        session_destroy();
        echo json_encode(['success' => true, 'message' => '已成功登出']);
        break;

    case 'check_session':
        if ($request_method !== 'GET') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['success' => true, 'isAuthenticated' => true, 'user' => ['id' => $_SESSION['user_id'], 'displayId' => $_SESSION['display_id'], 'points' => $_SESSION['points']]]);
        } else {
            echo json_encode(['success' => true, 'isAuthenticated' => false]);
        }
        break;
}
