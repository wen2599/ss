<?php
require_once __DIR__ . '/api_header.php';

write_log("------ get_lottery_results.php Entry Point ------");

$pdo = get_db_connection();
if (is_array($pdo) && isset($pdo['db_error'])) {
    $errorMsg = "Database connection error: " . $pdo['db_error'];
    write_log($errorMsg);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
if (!$pdo) {
    $errorMsg = "Database connection returned null.";
    write_log($errorMsg);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $lotteryType = isset($_GET['lottery_type']) ? urldecode($_GET['lottery_type']) : null;

    $sql = "SELECT id, lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date, created_at FROM lottery_results ";
    $params = [];

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = ?";
        $params[] = $lotteryType;
    }

    $sql .= " ORDER BY drawing_date DESC, issue_number DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'lottery_results' => $results]);

} catch (PDOException $e) {
    $errorMsg = "Error fetching lottery results: " . $e->getMessage();
    write_log($errorMsg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching lottery results.']);
}

write_log("------ get_lottery_results.php Exit Point ------");

?>
