<?php
// This endpoint retrieves email records from the database.

header('Content-Type: application/json');
require_once 'config.php';

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception("Database connection failed.");
    }

    $emailId = $_GET['id'] ?? null;

    if ($emailId) {
        // Fetch a single email by its ID.
        $stmt = $pdo->prepare("SELECT id, sender, recipient, subject, html_content, created_at FROM emails WHERE id = :id");
        $stmt->execute([':id' => $emailId]);
        $email = $stmt->fetch();

        if ($email) {
            echo json_encode(['success' => true, 'emails' => [$email]]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Email not found.']);
        }
    } else {
        // Fetch all emails, ordered by most recent.
        $stmt = $pdo->query("SELECT id, sender, recipient, subject, html_content, created_at FROM emails ORDER BY created_at DESC");
        $emails = $stmt->fetchAll();
        echo json_encode(['success' => true, 'emails' => $emails]);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Failed to get emails: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal Server Error: Could not retrieve emails.']);
}
?>