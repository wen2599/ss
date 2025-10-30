<?php
// backend/api/get_latest_draws.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers.php';

try {
    $pdo = get_db_connection();

    // The query finds the max `id` for each `lottery_type`, then joins back
    // to the main table to get the full record for each of those latest entries.
    $sql = "
        SELECT ld.*
        FROM lottery_draws ld
        INNER JOIN (
            SELECT lottery_type, MAX(id) as max_id
            FROM lottery_draws
            GROUP BY lottery_type
        ) latest ON ld.lottery_type = latest.lottery_type AND ld.id = latest.max_id
        ORDER BY ld.draw_date DESC, ld.id DESC;
    ";

    $stmt = $pdo->query($sql);
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse(['success' => true, 'data' => $draws]);

} catch (PDOException $e) {
    // In case of a database error, send a 500 server error response.
    // Avoid exposing detailed error messages in a production environment.
    sendJsonResponse(['success' => false, 'message' => 'Database query failed.'], 500);
} catch (Exception $e) {
    // Catch any other exceptions (e.g., failed DB connection).
    sendJsonResponse(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
