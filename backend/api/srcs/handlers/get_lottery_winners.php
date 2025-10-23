<?php
// get_lottery_winners.php

// This script retrieves the list of lottery winners from the database
// and returns them as a JSON response.

// Since this is included from index.php, we can assume the DB connection function is available.
// Also, the global error handlers and jsonResponse functions are available.

try {
    // The getDbConnection() function is defined in database/migration.php,
    // which is included before the router in index.php.
    $pdo = getDbConnection();

    // Prepare and execute the query to fetch all lottery winners
    $stmt = $pdo->query("SELECT id, username, prize, draw_date FROM lottery_winners ORDER BY draw_date DESC");

    // Fetch all results into an associative array
    $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return a successful JSON response with the data
    jsonResponse(200, ['status' => 'success', 'data' => $winners]);

} catch (PDOException $e) {
    // If a database error occurs, log it and return a generic server error
    error_log("Database Error: " . $e->getMessage());
    jsonError(500, 'Failed to retrieve lottery winners due to a server error.');
} catch (Exception $e) {
    // Catch any other unexpected errors
    error_log("General Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred.');
}
