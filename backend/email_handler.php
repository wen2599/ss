<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET');
// Cloudflare Worker sends 'worker_secret' as a form field.
$receivedToken = $_POST['worker_secret'] ?? '';

error_log("Email Handler Debug: secretToken (from env) = [" . ($secretToken ? $secretToken : "EMPTY") . "]");
error_log("Email Handler Debug: receivedToken (from POST) = [" . ($receivedToken ? $receivedToken : "EMPTY") . "]");

if (empty($secretToken) || $receivedToken !== $secretToken) {
    error_log("Email Handler: Forbidden - Invalid or missing secret token. Received: " . ($receivedToken ? $receivedToken : "[EMPTY]") . ", Expected: " . ($secretToken ? $secretToken : "[EMPTY]"));
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret token.']);
    exit;
}

// --- Process and Store Email Data ---

// Expecting multipart/form-data from the worker
$sender = $_POST['user_email'] ?? null;
$subject = $_POST['subject'] ?? 'No Subject'; // Worker might not send subject as explicit form field
$recipient = getenv('CATCH_ALL_EMAIL') ?? 'unknown@example.com'; // Worker does not send recipient via form data

$htmlContent = null;
$textContent = null;

// Check for HTML body as a file upload
if (isset($_FILES['html_body']) && $_FILES['html_body']['error'] === UPLOAD_ERR_OK) {
    $htmlContent = file_get_contents($_FILES['html_body']['tmp_name']);
}

// Check for chat_file (plain text content) as a file upload
if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
    $textContent = file_get_contents($_FILES['chat_file']['tmp_name']);
    // If subject not explicitly set, try to derive from text content (heuristic)
    if ($subject === 'No Subject' && $textContent) {
        if (preg_match('/Subject:\s*(.*)/i', $textContent, $matches)) {
            $subject = trim($matches[1]);
        }
    }
}

// Basic validation
if (empty($sender)) {
    error_log("Email Handler: Bad Request - Missing sender email.");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Missing sender email.']);
    exit;
}

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
