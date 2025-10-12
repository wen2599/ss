<?php

// --- Environment Variable Loading ---
function load_env() {
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = file_get_contents(__DIR__ . '/../.env');
        $lines = explode("\n", $dotenv);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

load_env();

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET'); 
$receivedToken = $_SERVER['HTTP_X_EMAIL_HANDLER_SECRET_TOKEN'] ?? '';

if (empty($secretToken) || $receivedToken !== $secretToken) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret token.']);
    exit;
}

// --- Process and Store Email Data ---

// Get the raw POST data (the email JSON from the worker).
$emailJson = file_get_contents('php://input');

// Basic validation to ensure we received something.
if (empty($emailJson)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: No data received.']);
    exit;
}

// Decode the JSON email data.
$emailData = json_decode($emailJson, true);

// Validate the decoded data.
if (json_last_error() !== JSON_ERROR_NONE || !isset($emailData['from']) || !isset($emailData['to']) || !isset($emailData['subject'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Invalid or incomplete email data.']);
    exit;
}

// --- Database Interaction ---
require_once 'config.php';

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception("Database connection failed.");
    }

    $stmt = $pdo->prepare(
        "INSERT INTO emails (sender, recipient, subject, html_content) VALUES (:sender, :recipient, :subject, :html_content)"
    );

    $stmt->execute([
        ':sender' => $emailData['from'],
        ':recipient' => $emailData['to'],
        ':subject' => $emailData['subject'],
        ':html_content' => $emailData['body'] ?? null
    ]);

    // --- Respond to the Worker ---
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Email successfully stored in the database.']);

} catch (Exception $e) {
    http_response_code(500);
    // Log the error for debugging, but don't expose details to the client.
    error_log("Failed to store email: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: Could not process the email.']);
}

?>
