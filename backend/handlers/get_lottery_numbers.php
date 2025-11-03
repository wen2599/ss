<?php
// backend/handlers/get_lottery_numbers.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $result = $conn->query("SELECT issue_date, number FROM lottery_numbers ORDER BY issue_date DESC");
    $numbers = [];
    while ($row = $result->fetch_assoc()) {
        $numbers[] = $row;
    }
    echo json_encode($numbers);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Get Lottery Numbers Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal Server Error']);
} finally {
    if (isset($conn)) $conn->close();
}
?>