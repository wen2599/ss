<?php
// File: backend/auth/logout.php

// api_header.php 已经启动了 session, 所以我们可以直接操作它

// 1. 清空所有 session 变量
$_SESSION = [];

// 2. 删除 session cookie
// 这是确保浏览器删除会话标识的关键步骤
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. 销毁服务器上的 session 数据
session_destroy();

// 4. 返回成功响应
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => '登出成功！']);
?>