<?php
// backend/db_connection.php

// 严格错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 加载环境变量
require_once __DIR__ . '/env_loader.php';

// 从 getenv 获取数据库凭证
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

// 检查是否所有凭证都已加载
if (empty($db_host) || empty($db_user) || empty($db_name)) {
    // 在生产环境中不应显示详细错误
    error_log("数据库凭证缺失或不完整。");
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => '服务器配置错误。']));
}

// 创建数据库连接
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接错误
if ($conn->connect_error) {
    error_log("数据库连接失败: " . $conn->connect_error);
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => '无法连接到数据库。']));
}

// 设置字符集为 UTF-8
$conn->set_charset("utf8mb4");
