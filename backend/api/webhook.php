<?php
// ===== Webhook Entry Point for Telegram Bot =====

// --- Error Reporting & Logging Setup ---
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Basic logging until bootstrap is complete
error_log("--- [BOOTSTRAP] Webhook execution started ---");

// --- Bootstrap Application ---
require_once __DIR__ . '/bootstrap.php';

// --- PSR-4 Autoloader ---
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Services\TelegramService;
use App\Controllers\TelegramController;

// --- Environment & Security ---
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$webhookSecret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? null;
$channelId = $_ENV['TELEGRAM_CHANNEL_ID'] ?? null;
$adminId = $_ENV['TELEGRAM_ADMIN_ID'] ?? null;

if (!$botToken || !$webhookSecret || !$channelId) {
    error_log("FATAL: Missing required environment variables (TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET, or TELEGRAM_CHANNEL_ID).");
    http_response_code(500);
    exit('Configuration Error');
}

$telegram_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($webhookSecret !== $telegram_header) {
    error_log("CRITICAL: Unauthorized webhook access attempt.");
    http_response_code(403);
    exit('Forbidden');
}

// --- Dependency Injection Setup ---
try {
    $pdo = get_db_connection();
    $telegramService = new TelegramService($botToken);
    // Note: The third argument for a logger is null as we don't have a formal logger implemented.
    $controller = new TelegramController($telegramService, $pdo, null, $channelId, $adminId);
} catch (\PDOException $e) {
    error_log("CRITICAL: Failed to establish database connection: " . $e->getMessage());
    // Notify admin even if DB is down
    if ($botToken && $adminId) {
        $emergencyService = new TelegramService($botToken);
        $emergencyService->sendMessage($adminId, "🚨 CRITICAL: Bot failed to connect to the database. Please check the server logs.");
    }
    http_response_code(500);
    exit('Database Connection Error');
}

// --- Process Request ---
$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(400);
    exit('Empty Request Body');
}

$update = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("ERROR: Invalid JSON received. " . json_last_error_msg());
    http_response_code(400);
    exit('Invalid JSON');
}

// --- Handle Webhook ---
$controller->handleWebhook($update);

// Respond to Telegram to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'success']);

error_log("--- [SUCCESS] Webhook execution finished ---");
