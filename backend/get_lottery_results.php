<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ get_lottery_results.php Entry Point ------");

$pdo = get_db_connection();
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

$processedResults = array_map(function($row) {
    $row['winning_numbers'] = json_decode($row['winning_numbers'], true) ?: [];
    $row['zodiac_signs'] = json_decode($row['zodiac_signs'], true) ?: [];
    $row['colors'] = json_decode($row['colors'], true) ?: [];
    return $row;
}, $results);

json_response('success', ['lottery_results' => $processedResults]);

write_log("------ get_lottery_results.php Exit Point ------");

?>