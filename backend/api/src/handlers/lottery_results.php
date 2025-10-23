<?php
declare(strict_types=1);

// Assumes jsonResponse and jsonError functions are available from index.php
// Assumes getDbConnection() is available from index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication --- (Optional based on requirements)
// For now, let's make it public as it was in the original implementation.
// If it needs to be protected, uncomment the following block:
/*
if (!isset($_SESSION['user_id'])) {
    jsonError(401, 'Unauthorized. Please log in.');
}
*/

$pdo = getDbConnection();

// --- Fetch Lottery Results ---
try {
    // Get the most recent 20 results
    $stmt = $pdo->query('SELECT * FROM lottery_results ORDER BY created_at DESC LIMIT 20');
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Database error fetching lottery results: ' . $e->getMessage());
    jsonError(500, 'Failed to retrieve lottery results.');
}

// --- Data Transformation ---
// Transform the database results into the desired JSON structure.
$transformedResults = [];
foreach ($results as $row) {
    $transformedResults[] = [
        'type' => $row['type'],
        'results' => json_decode($row['numbers']), // Assuming numbers are stored as a JSON string
        'date' => (new DateTime($row['created_at']))->format('Y-m-d'),
    ];
}

// --- Success Response ---
jsonResponse(200, [
    'status' => 'success',
    'data' => $transformedResults
]);
