<?php
require_once __DIR__ . '/cors_headers.php';
require_once __DIR__ . '/../db_connection.php';

$results = [];
$sql = "SELECT id, issue_number, draw_date, numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
}

$conn->close();

echo json_encode($results);
?>