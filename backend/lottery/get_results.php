<?php
// File: backend/lottery/get_results.php (Simplified Version)

// DO NOT add require_once for db_operations.php or config.php here.
// index.php is responsible for loading them before including this file.

// We should already have a db_connection function available.
if (!function_exists('get_db_connection')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Core function get_db_connection() is missing.']);
    exit;
}

try {
    $pdo = get_db_connection();

    $sql = "
        SELECT r1.*
        FROM lottery_results r1
        JOIN (
            SELECT lottery_type, MAX(id) AS max_id
            FROM lottery_results
            GROUP BY lottery_type
        ) r2 ON r1.lottery_type = r2.lottery_type AND r1.id = r2.max_id
    ";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped_results = [];
    foreach ($results as $row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $decoded = json_decode($row[$key]);
            $row[$key] = $decoded ?: [];
        }
        $grouped_results[$row['lottery_type']] = $row;
    }

    $lottery_types = ['香港六合彩', '新澳门六合彩', '老澳门六合彩'];
    $final_data = [];
    foreach ($lottery_types as $type) {
        $final_data[$type] = $grouped_results[$type] ?? null;
    }

    echo json_encode(['status' => 'success', 'data' => $final_data]);

} catch (PDOException $e) {
    http_response_code(500);
    // 在调试期间，暴露详细的错误信息
    echo json_encode([
        'status' => 'error', 
        'message' => 'A database error occurred.',
        'details' => $e->getMessage() // <-- 输出具体DB错误
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    // 捕获其他所有错误
     echo json_encode([
        'status' => 'error', 
        'message' => 'A general error occurred.',
        'details' => $e->getMessage()
    ]);
}
?>