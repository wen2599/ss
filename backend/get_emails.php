<?php
// backend/get_emails.php

require_once __DIR__ . '/api_header.php';

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to view emails.']);
    exit;
}

$pdo = get_db_connection();
$userId = $_SESSION['user_id'];
$emailId = $_GET['id'] ?? null;

try {
    if ($emailId) {
        // --- Fetch a single email by ID ---
        $stmt = $pdo->prepare(
            "SELECT id, from_sender, subject, raw_email, parsed_content, received_at
             FROM user_emails
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$emailId, $userId]);
        $email = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($email) {
            echo json_encode(['status' => 'success', 'emails' => [$email]]);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => '未找到该账单']);
        }
    } else {
        // --- Fetch all emails for the user ---
        $stmt = $pdo->prepare(
            "SELECT id, from_sender, subject, received_at
             FROM user_emails
             WHERE user_id = ? 
             ORDER BY received_at DESC"
        );
        $stmt->execute([$userId]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'emails' => $emails]);
    }
} catch (PDOException $e) {
    error_log("Error fetching emails: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching emails.']);
}
