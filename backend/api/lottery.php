<?php
// api/lottery.php

function handle_get_numbers($conn) {
    $result = $conn->query("SELECT issue_number, winning_numbers, draw_date FROM lottery_numbers ORDER BY draw_date DESC, issue_number DESC");
    $numbers = [];
    while($row = $result->fetch_assoc()) {
        $numbers[] = $row;
    }
    return ['success' => true, 'data' => $numbers];
}

function handle_add_number($conn) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    $admin_token = $env['ADMIN_API_TOKEN'];
    
    // 从 GET/POST 请求中获取参数
    $token = $_REQUEST['token'] ?? null;
    $issue_number = $_REQUEST['issue_number'] ?? null;
    $winning_numbers = $_REQUEST['winning_numbers'] ?? null;
    $draw_date = $_REQUEST['draw_date'] ?? null; // 格式: YYYY-MM-DD

    if ($token !== $admin_token) {
        http_response_code(401);
        return ['success' => false, 'message' => '认证失败'];
    }

    if (empty($issue_number) || empty($winning_numbers) || empty($draw_date)) {
        return ['success' => false, 'message' => '缺少必要参数 (issue_number, winning_numbers, draw_date)'];
    }

    $stmt = $conn->prepare("INSERT INTO lottery_numbers (issue_number, winning_numbers, draw_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $issue_number, $winning_numbers, $draw_date);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => '开奖号码添加成功'];
    } else {
        // 检查是否是唯一键冲突
        if ($conn->errno == 1062) {
            return ['success' => false, 'message' => '该期号已存在'];
        }
        return ['success' => false, 'message' => '添加失败: ' . $stmt->error];
    }
}
?>