<?php
// backend/api/cors_headers.php

// 指定允许的源
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

// 检查请求源是否存在
if (isset($_SERVER['HTTP_ORIGIN'])) {
    // 如果请求源是允许的源，则设置响应头
    if ($_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
        header("Access-Control-Allow-Origin: " . $allowed_origin);
    }
} else {
    // 对于没有源的请求（例如，服务器到服务器的请求或一些工具），可以选择允许或拒绝
    // 为了安全起见，可以选择只允许明确指定的源
    // 如果您希望允许 Postman 或类似的工具，可能需要更灵活的策略
}

// 设置允许的方法
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// 设置允许的头部
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// 设置凭证许可
header("Access-Control-Allow-Credentials: true");

// 响应预检请求 (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // 发送 200 OK 状态码并退出脚本
    http_response_code(200);
    exit;
}
