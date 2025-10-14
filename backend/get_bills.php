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
    // Check if a specific bill ID is requested
    if (isset($_GET['id'])) {
        $billId = $_GET['id'];
        $stmt = $pdo->prepare("SELECT id, sender, subject, bill_date, amount, currency, pdf_url, created_at, html_content FROM bills WHERE id = ?");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bill) {
            echo json_encode(['status' => 'success', 'bills' => [$bill]]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Bill not found.']);
        }
    } else {
        // Fetch all bills, including html_content for consistency
        $stmt = $pdo->prepare("SELECT id, sender, subject, bill_date, amount, currency, pdf_url, created_at, html_content FROM bills ORDER BY bill_date DESC");
        $stmt->execute();
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'bills' => $bills]);
    }

} catch (PDOException $e) {
    // Log the error and return a generic error message
    error_log("Error fetching bills: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching bills.']);
}
?>