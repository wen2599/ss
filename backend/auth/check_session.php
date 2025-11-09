<?php
// File: backend/auth/check_session.php

// api_header.php 已经启动了 session

// 检查 session 中是否存在 user_id
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // 用户已登录，返回用户信息
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'isAuthenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'] ?? 'N/A' // 提供一个默认值以防万一
        ]
    ]);
} else {
    // 用户未登录
    http_response_code(200); // 即使未登录，请求本身也是成功的
    echo json_encode([
        'status' => 'success',
        'isAuthenticated' => false,
        'user' => null
    ]);
}
?>