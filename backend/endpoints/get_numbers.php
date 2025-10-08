<?php
// backend/endpoints/get_numbers.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

header('Content-Type: application/json');
$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// The different lottery types we want to fetch
$lottery_types = ['新澳门六合彩', '香港六合彩', '老澳21.30'];

// This SQL query is designed to get the latest entry for EACH lottery type.
// It uses a more compatible subquery approach instead of window functions
// to ensure it works on older versions of MySQL/MariaDB.
$sql = "
    SELECT t1.lottery_type, t1.issue, t1.numbers, t1.zodiacs, t1.colors
    FROM lottery_results t1
    INNER JOIN (
        SELECT lottery_type, MAX(received_at) as max_received_at
        FROM lottery_results
        WHERE lottery_type IN (?, ?, ?)
        GROUP BY lottery_type
    ) t2 ON t1.lottery_type = t2.lottery_type AND t1.received_at = t2.max_received_at;
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database prepare statement failed: ' . $conn->error]);
    exit;
}

// Bind the lottery types to the placeholders
$stmt->bind_param("sss", $lottery_types[0], $lottery_types[1], $lottery_types[2]);
$stmt->execute();
$result = $stmt->get_result();

$results = [];
while ($row = $result->fetch_assoc()) {
    // The data is stored as JSON strings, so we decode them into arrays
    $row['numbers'] = json_decode($row['numbers']);
    $row['zodiacs'] = json_decode($row['zodiacs']);
    $row['colors'] = json_decode($row['colors']);
    $results[$row['lottery_type']] = $row;
}

$stmt->close();
$conn->close();

// Ensure all requested lottery types are present in the output, even if they have no data yet
$final_response = [];
foreach ($lottery_types as $type) {
    $final_response[$type] = $results[$type] ?? null; // If not found in DB, value will be null
}

echo json_encode($final_response);

?>