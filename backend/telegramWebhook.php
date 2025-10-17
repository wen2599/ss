<?php
// Simplified telegramWebhook.php for debugging

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$logFile = __DIR__ . '/telegram_debug.log';

// --- Basic Logging ---
$now = date('[Y-m-d H:i:s]');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '[HEADER NOT SET]';
$rawBody = file_get_contents('php://input');

$logMessage = "{$now} [SIMPLIFIED DEBUG] Method={$method}, URI={$uri}, SecretHeader={$secretHeader}\n";
$logMessage .= "Raw Body: " . $rawBody . "\n\n";

// Use @ to suppress file permission errors if they occur
@file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

// Respond to Telegram so it doesn't keep retrying
http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Request received and logged.']);
?>
