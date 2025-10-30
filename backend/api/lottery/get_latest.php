<?php
// 文件名: get_latest.php
// 路径: backend/api/lottery/get_latest.php

// --- DEBUGGING: 强制显示所有PHP错误 ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 引入数据库连接
// 使用 __DIR__ 确保路径总是正确的
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';

try {
    $db = get_db_connection();
    
    // 查询最新的一条记录
    $stmt = $db->query("SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        json_response($result, 200);
    } else {
        json_response(['message' => 'No lottery results found.'], 404);
    }

} catch (PDOException $e) {
    // 捕获数据库查询错误
    error_log("API Error in get_latest.php: " . $e->getMessage());
    json_response(['message' => 'A database error occurred.'], 500);
} catch (Exception $e) {
    // 捕获其他一般性错误
    error_log("API Error in get_latest.php: " . $e->getMessage());
    json_response(['message' => 'An unexpected error occurred.'], 500);
}