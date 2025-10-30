<?php
// File: backend/api/email_webhook.php
// Description: Secure endpoint to receive parsed emails from a Cloudflare worker.

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secrets.php';

// --- Response Helper ---
function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

// --- Security Check ---
$provided_secret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? null;
$expected_secret = get_email_webhook_secret();

if (!$expected_secret) {
    error_log("CRITICAL: EMAIL_WEBHOOK_SECRET is not configured in .env file.");
    json_response(500, ['success' => false, 'error' => 'Server configuration error.']);
}

if ($provided_secret !== $expected_secret) {
    json_response(403, ['success' => false, 'error' => 'Forbidden: Invalid or missing webhook secret.']);
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method Not Allowed']);
}

$raw_data = file_get_contents('php://input');
$email_data = json_decode($raw_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    json_response(400, ['success' => false, 'error' => 'Invalid JSON payload.']);
}

// --- Data Validation ---
$to_email = $email_data['to'] ?? null;
$from_address = $email_data['from'] ?? null;
$subject = $email_data['subject'] ?? 'No Subject';
$body = $email_data['body'] ?? '';

if (!$to_email || !$from_address) {
    json_response(400, ['success' => false, 'error' => 'Missing required fields: to, from.']);
}

// --- Database Operations ---
try {
    $conn = get_db_connection();
} catch (Exception $e) {
    error_log("Email Webhook - DB connection failed: " . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Database connection failed.']);
}

// 1. Find the user by their email address
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $to_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    // Important: Respond 200 OK so the worker doesn't retry for non-existent users.
    json_response(200, ['success' => true, 'message' => 'Email received for a non-registered user. Discarding.']);
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// 2. Store the email in the database
$sql = "INSERT INTO received_emails (user_id, from_address, subject, body) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Email Webhook - DB statement prepare failed: " . $conn->error);
    json_response(500, ['success' => false, 'error' => 'Database statement preparation failed.']);
}

$stmt->bind_param('isss', $user_id, $from_address, $subject, $body);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    json_response(201, ['success' => true, 'message' => 'Email successfully received and stored.']);
} else {
    error_log("Email Webhook - DB execute failed: " . $stmt->error);
    json_response(500, ['success' => false, 'error' => 'Failed to store the email.']);
}
?>