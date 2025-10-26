<?php
require_once __DIR__ . '/../bootstrap.php';

header("Content-Type: application/json; charset=UTF-8");

global $db_connection;

$lottery_types = ['新澳门六合彩', '香港六合彩', '老澳21.30'];

try {
    // This query uses a subquery to find the max ID (latest entry) for each lottery type.
    // It's more efficient and guarantees exactly one result per type.
    $query = "
        SELECT ld.*
        FROM lottery_draws ld
        INNER JOIN (
            SELECT lottery_type, MAX(id) as max_id
            FROM lottery_draws
            WHERE lottery_type IN (?, ?, ?)
            GROUP BY lottery_type
        ) as latest ON ld.lottery_type = latest.lottery_type AND ld.id = latest.max_id
    ";

    $stmt = $db_connection->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $db_connection->error);
    }

    // Bind the lottery types to the IN clause placeholders
    $stmt->bind_param("sss", $lottery_types[0], $lottery_types[1], $lottery_types[2]);
    $stmt->execute();
    $result = $stmt->get_result();

    $latest_draws = [];
    while ($row = $result->fetch_assoc()) {
        $latest_draws[] = $row;
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
