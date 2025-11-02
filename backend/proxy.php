<?php
// backend/proxy.php

require_once 'config.php';

// --- CORS Pre-flight (OPTIONS) Request Handling ---
// 浏览器在发送跨域的POST/PUT等请求前，会先发送一个OPTIONS请求来“预检”
// 我们需要正确地响应它，告诉浏览器我们允许跨域
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // 允许任何来源的跨域请求。在生产环境中，可以指定为你的前端域名
    header("Access-Control-Allow-Origin: *"); 
    // 允许的请求方法
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    // 允许的请求头
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Worker-Secret");
    // 响应204 No Content，并立即退出脚本
    http_response_code(204);
    exit;
}

// --- 安全性：验证来自 Cloudflare Worker 的秘密请求头 ---
$worker_secret_header = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$expected_secret = $_ENV['CLOUDFLARE_WORKER_SECRET'] ?? '816429fb-1649-4e48-9288-7629893311a6';

if (empty($expected_secret) || $worker_secret_header !== $expected_secret) {
    http_response_code(403);
    header('Content-Type: application/json');
    // 添加 Access-Control-Allow-Origin 头，以便前端能读到这个JSON错误信息
    header("Access-Control-Allow-Origin: *");
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing worker secret.']);
    exit;
}

// 密钥验证通过，包含核心API逻辑
require 'api.php';