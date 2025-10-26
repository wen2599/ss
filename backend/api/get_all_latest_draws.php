<?php
require_once __DIR__ . '/../bootstrap.php';

header("Content-Type: application/json; charset=UTF-8");

global $db_connection;

$lottery_types = ['新澳门六合彩', '香港六合彩', '老澳21.30'];
$latest_draws = [];

try {
    // Prepare the statement once
    $stmt = $db_connection->prepare(
        "SELECT lottery_type, draw_period, draw_date, numbers, zodiacs, colors
         FROM lottery_draws
         WHERE lottery_type = ?
         ORDER BY draw_date DESC, draw_period DESC
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $db_connection->error);
    }

    // Loop through each lottery type and execute the query
    foreach ($lottery_types as $type) {
        $stmt->bind_param("s", $type);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($draw = $result->fetch_assoc()) {
            $latest_draws[] = $draw;
        }
    }

    $stmt->close();

    http_response_code(200);
    echo json_encode($latest_draws);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_all_latest_draws.php: " . $e->getMessage());
    echo json_encode(["message" => "An internal server error occurred."]);
} finally {
    if ($db_connection) {
        $db_connection->close();
    }
}
