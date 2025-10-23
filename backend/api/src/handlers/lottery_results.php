<?php
// Included from /api/index.php

// This script fetches and returns lottery results.

// Establish a database connection (already available via index.php)
$pdoconn = getDbConnection();

// --- Fetch Lottery Winners ---
$stmt_select = $pdoconn->prepare("SELECT `username`, `prize`, `draw_date` FROM `lottery_winners` ORDER BY `draw_date` DESC");
$stmt_select->execute();
$winners = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

// --- API Response ---
jsonResponse(200, [
    'status' => 'success',
    'message' => 'Lottery results fetched successfully.',
    'data' => $winners
]);
?>
