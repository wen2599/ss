<?php
// backend/proxy.php

// ！！！这是 Cloudflare Worker 将要访问的唯一文件 ！！！

require_once 'config.php'; // 加载.env配置

// --- 安全性：验证来自 Cloudflare Worker 的秘密请求头 ---
$worker_secret_header = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$expected_secret = $_ENV['CLOUDFLARE_WORKER_SECRET'] ?? '';

// 如果密钥为空或不匹配，立即拒绝请求
if (empty($expected_secret) || $worker_secret_header !== $expected_secret) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing worker secret.']);
    exit;
}

// 密钥验证通过，现在我们可以安全地包含核心API逻辑
// 我们将请求的所有参数都传递给 api.php
// 这种包含方式让 api.php 可以访问所有变量和超全局变量
require 'api.php';
?>