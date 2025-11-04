<?php
// backend/api/lottery.php

require_once __DIR__ . '/../config/database.php';

function handle_get_latest_lottery_number() {
    $mysqli = get_db_connection();

    // 查询最新的一条记录
    $result = $mysqli->query("SELECT number, created_at FROM lottery_numbers ORDER BY created_at DESC LIMIT 1");

    if ($result && $result->num_rows > 0) {
        $latest_number = $result->fetch_assoc();
        http_response_code(200);
        echo json_encode($latest_number);
    } else {
        // 数据库中还没有任何号码
        http_response_code(404);
        echo json_encode(['message' => '暂无开奖号码']);
    }

    $mysqli->close();
}