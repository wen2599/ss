<?php
// backend/api/get_latest_draws.php

// --- Temporary Debugging ---
// Enable error reporting to see the cause of the 500 error directly.
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- End Temporary Debugging ---

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

} catch (Exception $e) {
    // --- Temporary Debugging ---
    // We are temporarily exposing the raw exception message to the client
    // to diagnose the root cause of the setup failure on the server.
    sendJsonResponse(['success' => false, 'message' => 'Caught Exception: ' . $e->getMessage()], 500);
    // --- End Temporary Debugging ---
}
