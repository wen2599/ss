<?php
ini_set('display_errors', '1'); // Temporarily enable display errors for debugging
error_reporting(E_ALL); // Report all PHP errors

error_log("--- [BOOTSTRAP LOG] webhook.php execution started ---");

if (isset($_GET['ping']) && $_GET['ping'] === '1') {
    header('Content-Type: text/plain');
    echo 'pong';
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/bootstrap.php';
error_log("--- [INFO] bootstrap.php included ---");

// Temporarily override APP_DEBUG for debugging purposes
$_ENV['APP_DEBUG'] = 'true';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    error_log("Autoloading: " . $class . " from file: " . $file); // Debug autoloader
    if (file_exists($file)) {
        require $file;
        error_log("Successfully loaded: " . $file); // Debug autoloader success
    } else {
        error_log("ERROR: Autoloading failed, file not found: " . $file); // Debug autoloader failure
    }
});

use App\Controllers\TelegramController;
use App\Controllers\BaseController; // Include BaseController for potential errors

error_log("--- [INFO] After autoloader setup and use statements ---");

if (!isset($_ENV['TELEGRAM_BOT_TOKEN']) || !isset($_ENV['TELEGRAM_CHANNEL_ID'])) {
    error_log('FATAL: TELEGRAM_BOT_TOKEN or TELEGRAM_CHANNEL_ID are not set in the .env file.');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error.']);
    exit;
}

error_log("--- [INFO] Telegram webhook received ---");
$input = file_get_contents('php://input');
error_log("Raw input: " . $input); // Log raw input for debugging Telegram requests

try {
    $update = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Invalid JSON received: " . json_last_error_msg());
    }
    error_log("--- [INFO] JSON input decoded successfully ---");

    $controller = new TelegramController();
    error_log("--- [INFO] TelegramController instantiated ---");

    $controller->handleWebhook($update);
    error_log("--- [INFO] handleWebhook executed ---");
    
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed.']);

} catch (\Throwable $e) { // Catch Throwable to include Errors (e.g., ParseError, TypeError)
    error_log('FATAL ERROR in Telegram webhook handler: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    
    $response = ['status' => 'error', 'message' => 'An error occurred while processing the request.'];
    // In debug mode, provide full error details
    $response['details'] = $e->getMessage();
    $response['file'] = $e->getFile();
    $response['line'] = $e->getLine();
    $response['trace'] = explode("\n", $e->getTraceAsString());
    echo json_encode($response);
}
