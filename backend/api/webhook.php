<?php
error_log("--- [BOOTSTRAP LOG] webhook.php execution started ---");

if (isset($_GET['ping']) && $_GET['ping'] === '1') {
    header('Content-Type: text/plain');
    echo 'pong';
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/bootstrap.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\Controllers\\';
    $base_dir = __DIR__ . '/src/Controllers/';
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

use App\Controllers\TelegramController;

// CORRECTED: Changed LOTTERY_CHANNEL_ID to TELEGRAM_CHANNEL_ID to match the .env file
if (!isset($_ENV['TELEGRAM_BOT_TOKEN']) || !isset($_ENV['TELEGRAM_CHANNEL_ID'])) {
    error_log('FATAL: TELEGRAM_BOT_TOKEN or TELEGRAM_CHANNEL_ID are not set in the .env file.');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error.']);
    exit;
}

error_log("--- [INFO] Telegram webhook received ---");
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);

try {
    $update = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Invalid JSON received: " . json_last_error_msg());
    }

    $controller = new TelegramController();
    $controller->handleWebhook($update);
    
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed.']);

} catch (\Exception $e) {
    error_log('Error in Telegram webhook handler: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while processing the request.']);
}
