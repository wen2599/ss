<?php
// backend/api/cors_headers.php

// 定义允许的源
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

// 无论请求源如何，都设置 Access-Control-Allow-Origin
// 这有助于确保即使在预检请求中也能正确设置头部，排除服务器配置问题。
header("Access-Control-Allow-Origin: " . $allowed_origin);

// 设置允许的方法
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// 设置允许的头部
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// 设置凭证许可
header("Access-Control-Allow-Credentials: true");

// 设置预检请求的缓存时间（可选，但推荐）
header("Access-Control-Max-Age: 86400"); // 缓存 1 天

// 响应预检请求 (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // 发送 200 OK 状态码并退出脚本
    http_response_code(200);
    exit;
}
