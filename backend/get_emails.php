<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ get_emails.php Entry Point ------");

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    json_response('error', 'Unauthorized: User not logged in.', 401);
}

$userId = $_SESSION['user_id'];
$emailId = $_GET['id'] ?? null;

try {
    $pdo = get_db_connection();
    if ($emailId) {
        // --- Fetch a single email by ID ---
        $stmt = $pdo->prepare(
            "SELECT id, sender, recipient, subject, html_content, created_at 
             FROM emails 
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$emailId, $userId]);
        $email = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($email) {
            json_response('success', ['email' => $email]); // Return single email directly
        } else {
            write_log("Email ID {$emailId} not found for user {$userId} or unauthorized.");
            json_response('error', '未找到该邮件或无权查看。', 404);
        }
    } else {
        // --- Fetch all emails for the user ---
        $stmt = $pdo->prepare(
            "SELECT id, sender, subject, created_at 
             FROM emails 
             WHERE user_id = ? 
             ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response('success', ['emails' => $emails]);
    }
} catch (PDOException $e) {
    write_log("Database error in get_emails.php: " . $e->getMessage());
    json_response('error', 'An error occurred while fetching emails.', 500);
}

write_log("------ get_emails.php Exit Point ------");

?>