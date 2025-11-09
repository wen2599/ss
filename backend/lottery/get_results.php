<?php
// File: backend/lottery/get_results.php (Debug Enhanced)

// 在调试期间，强制显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// index.php 应该已经包含了 api_header.php 和 config.php
// 但为了独立测试的健壮性，我们再次包含
if (!defined('DB_OPERATIONS_LOADED')) {
    require_once __DIR__ . '/../db_operations.php';
}

try {
    $pdo = get_db_connection();
    
    // 增加一个明确的检查，看数据库连接是否成功
    if (!$pdo instanceof PDO) {
        // 如果 get_db_connection() 返回的不是一个 PDO 对象，说明连接失败
        throw new Exception("Failed to get a valid database connection object.");
    }

    $stmt = $pdo->query("SELECT * FROM lottery_results ORDER BY drawing_date DESC, issue_number DESC LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = array_map(function($row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            // 增加检查，防止字段不存在
            if (isset($row[$key])) {
                $decoded = json_decode($row[$key]);
                $row[$key] = $decoded ?: [];
            } else {
                $row[$key] = []; // 如果字段缺失，给个默认值
            }
        }
        return $row;
    }, $results);

    // 如果成功，也要确保 header 是 json
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['status' => 'success', 'data' => $processed]);

} catch (Throwable $e) { // 使用 Throwable 可以捕获 Error 和 Exception
    // 在调试阶段，我们返回详细的错误信息
    http_response_code(500);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching lottery results.',
        'error_details' => [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString() // 完整的堆栈跟踪
        ]
    ]);
}
?>