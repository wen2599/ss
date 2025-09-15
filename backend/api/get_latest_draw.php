<?php
// backend/api/get_latest_draw.php
require_once 'config.php';
require_once 'db_connect.php'; // Use the new connector
header('Content-Type: application/json');

$conn = db_connect();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    $sql = "
        SELECT d.period, d.winning_numbers, d.draw_time, d.lottery_type
        FROM draws d
        INNER JOIN (
            SELECT lottery_type, MAX(draw_time) AS max_draw_time
            FROM draws
            GROUP BY lottery_type
        ) AS latest_draws ON d.lottery_type = latest_draws.lottery_type AND d.draw_time = latest_draws.max_draw_time
    ";
    $result = $conn->query($sql);
    $latest_draws = [];
    while($row = $result->fetch_assoc()) {
        $latest_draws[$row['lottery_type']] = $row;
    }

    if (!empty($latest_draws)) {
        echo json_encode(['success' => true, 'data' => $latest_draws]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No draw data found.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

$conn->close();
?>
