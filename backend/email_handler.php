<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET');
// Cloudflare Worker sends 'worker_secret' as a form field.
$receivedToken = $_POST['worker_secret'] ?? '';

if (empty($secretToken) || $receivedToken !== $secretToken) {
    error_log("Email Handler: Forbidden - Invalid or missing secret token. Received: " . $receivedToken . ", Expected: " . ($secretToken ? "[SET]" : "[EMPTY]"));
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret token.']);
    exit;
}

// --- Process and Store Email Data ---

// Expecting multipart/form-data from the worker
$sender = $_POST['user_email'] ?? null;
$subject = $_POST['subject'] ?? 'No Subject'; // Assuming subject might be in form data or we can derive it.
$htmlContent = $_POST['html_body'] ?? null;
$textContent = $_POST['raw_email_file'] ?? null; // For simplicity, we'll get content from this for now.

// Placeholder for recipient, as Worker does not send it explicitly in form data.
// You might need to adjust your worker or backend logic to get a specific recipient.
$recipient = getenv('CATCH_ALL_EMAIL') ?? 'unknown@example.com'; // Use a default or environment variable

// Basic validation
if (empty($sender)) {
    error_log("Email Handler: Bad Request - Missing sender email.");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Missing sender email.']);
    exit;
}

// If subject is not in POST, try to get it from the raw_email_file content (simple heuristic)
if ($subject === 'No Subject' && $textContent) {
    if (preg_match('/Subject:\s*(.*)/i', $textContent, $matches)) {
        $subject = trim($matches[1]);
    }
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
