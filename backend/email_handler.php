<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET');
// Cloudflare Worker sends 'X-Email-Handler-Secret-Token' in the header.
$receivedToken = $_SERVER['HTTP_X_EMAIL_HANDLER_SECRET_TOKEN'] ?? '';

error_log("Email Handler Debug: secretToken (from env) = [" . ($secretToken ? $secretToken : "EMPTY") . "]");
error_log("Email Handler Debug: receivedToken (from header) = [" . ($receivedToken ? $receivedToken : "EMPTY") . "]");

if (empty($secretToken) || $receivedToken !== $secretToken) {
    error_log("Email Handler: Forbidden - Invalid or missing secret token. Received: " . ($receivedToken ? $receivedToken : "[EMPTY]") . ", Expected: " . ($secretToken ? $secretToken : "[EMPTY]"));
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret token.']);
    exit;
}

// --- Process and Store Email Data ---

// Expecting application/json from the worker
$rawInput = file_get_contents('php://input');
$emailData = json_decode($rawInput, true);

// Basic validation
if (json_last_error() !== JSON_ERROR_NONE || !isset($emailData['from']) || !isset($emailData['to']) || !isset($emailData['subject'])) {
    error_log("Email Handler: Bad Request - Invalid or incomplete JSON email data. Raw input: " . $rawInput);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Invalid or incomplete email data.']);
    exit;
}

$sender = $emailData['from'];
$recipient = $emailData['to']; // Worker sends 'to' explicitly
$subject = $emailData['subject'];
$htmlContent = $emailData['html'] ?? null;
$textContent = $emailData['body'] ?? null;

// --- Database Interaction ---
$pdo = get_db_connection();
if (!$pdo) {
    error_log("Email Handler: Database connection failed.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the database.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO emails (sender, recipient, subject, html_content) VALUES (:sender, :recipient, :subject, :html_content)"
    );

    $isSuccess = $stmt->execute([
        ':sender' => $sender,
        ':recipient' => $recipient,
        ':subject' => $subject,
        ':html_content' => $htmlContent // Store HTML content if available
    ]);

    if ($isSuccess) {
        error_log("Email Handler: Email from " . $sender . " successfully stored.");
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Email successfully stored in the database.']);
    } else {
        error_log("Email Handler: Failed to store email from " . $sender . " - PDO execute failed.");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: Could not store the email.']);
    }

} catch (PDOException $e) {
    error_log("Email Handler: Database error during storage: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: Database error during email storage.']);
}

?>
