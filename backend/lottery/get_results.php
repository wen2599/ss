<?php
// File: backend/lottery/get_results.php (Grouped Latest Version)

require_once __DIR__ . '/../db_operations.php';

try {
    $pdo = get_db_connection();

    // 这是一个更高级的 SQL 查询，用于获取每个 lottery_type 的最新记录。
    // 工作原理：
    // 1. 内层子查询 `(SELECT lottery_type, MAX(id) as max_id FROM lottery_results GROUP BY lottery_type)`
    //    会找出每个彩票类型中 ID 最大（也就是最新）的那条记录的 ID。
    // 2. 外层查询通过 `JOIN` 将原始表与这个结果连接起来，只保留那些 ID 匹配的记录。
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

    // 将结果从一个扁平数组，转换为一个以 lottery_type 为键的关联数组，方便前端使用。
    $grouped_results = [];
    foreach ($results as $row) {
        // 解码 JSON 字段
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $decoded = json_decode($row[$key]);
            $row[$key] = $decoded ?: [];
        }
        // 按彩票类型分组
        $grouped_results[$row['lottery_type']] = $row;
    }

    // 定义前端期望的三种彩票类型，确保即使数据库中没有某个类型的数据，前端也能收到一个空占位
    $lottery_types = ['香港六合彩', '新澳门六合彩', '老澳门六合彩'];
    $final_data = [];
    foreach ($lottery_types as $type) {
        $final_data[$type] = $grouped_results[$type] ?? null; // 如果存在则使用，否则为 null
    }

    echo json_encode(['status' => 'success', 'data' => $final_data]);

} catch (PDOException $e) {
    http_response_code(500);
    // 调试时可以打开下面这行来查看详细错误
    // echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    echo json_encode(['status' => 'error', 'message' => 'Could not fetch lottery results.']);
}
?>