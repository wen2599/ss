<?php
// backend/get_bills.php

require_once __DIR__ . '/api_header.php';

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to view bills.']);
    exit;
}

$pdo = get_db_connection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the database.']);
    exit;
}

try {
    // Prepare and execute the query to fetch all bills from the 'bills' table
    $stmt = $pdo->prepare("SELECT id, sender, subject, bill_date, amount, currency, pdf_url, created_at FROM bills ORDER BY bill_date DESC");
    $stmt->execute();
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the bills as a JSON response
    echo json_encode(['status' => 'success', 'bills' => $bills]);

} catch (PDOException $e) {
    // Log the error and return a generic error message
    error_log("Error fetching bills: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching bills.']);
}
?>