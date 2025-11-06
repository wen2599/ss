<?php
require_once __DIR__ . '/../core/initialize.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response(405, 'Method Not Allowed');
}

// Security Check: Verify secret token from the worker
$auth_header = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($auth_header !== $_ENV['EMAIL_WORKER_SECRET']) {
    error_response(403, 'Forbidden: Invalid token');
}

$data = json_decode(file_get_contents('php://input'), true);
$sender_email = $data['from'] ?? null;
$raw_content = $data['raw_content'] ?? null;

if (!$sender_email || !$raw_content) {
    error_response(400, 'Missing sender or content.');
}

// Find user by sender email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$sender_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // User found, store the email
    $stmt = $pdo->prepare("INSERT INTO raw_emails (user_id, raw_content) VALUES (?, ?)");
    $stmt->execute([$user['id'], $raw_content]);
    json_response(201, ['message' => 'Email received and stored.']);
} else {
    // Per our discussion: Silently discard if sender is not a registered user
    json_response(200, ['message' => 'Sender not registered. Email discarded.']);
}
