<?php
// backend/get_emails.php
// This endpoint is designed to receive incoming email data, typically from a Cloudflare Worker
// or a similar email forwarding service. It then uses email_handler.php to process the email.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/email_handler.php';

header('Content-Type: application/json');

// --- Security Check ---
// It's CRUCIAL to secure this endpoint.
// Implement a robust authentication mechanism (e.g., a shared secret, API key, or signed request)
// to ensure only your Cloudflare Worker can access this endpoint.
// Example: Check for a custom header with a pre-shared key.
$sharedSecret = $_ENV['EMAIL_HANDLER_SECRET'] ?? ''; 
$incomingSecret = $_SERVER['HTTP_X_EMAIL_WORKER_SECRET'] ?? '';

if ($incomingSecret !== $sharedSecret || empty($sharedSecret)) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized access or missing secret.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $emailData = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload.']);
        exit();
    }

    // Expected fields from Cloudflare Worker
    $rawEmail = $emailData['rawEmail'] ?? null;
    $subject = $emailData['subject'] ?? 'No Subject';
    $recipient = $emailData['recipient'] ?? null; // The email address this email was sent to

    if (empty($rawEmail) || empty($recipient)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing raw email content or recipient.']);
        exit();
    }

    // Process the email using the email_handler function
    $result = processIncomingEmail($pdo, $rawEmail, $subject, $recipient);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['message' => $result['message'], 'billId' => $result['billId'] ?? null]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $result['message']]);
    }

} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method.']);
}
