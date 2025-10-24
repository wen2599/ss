<?php

// Set the content type to JSON, as is common for API endpoints.
header('Content-Type: application/json');

// --- 1. Bootstrap the Application ---
// This includes environment variables, database connection, and error handling.
require_once __DIR__ . '/bootstrap.php';

// --- 2. Set up Autoloader for Controllers ---
// This simple autoloader looks for class files in the `src/Controllers` directory.
spl_autoload_register(function ($class) {
    // A simple autoloader that expects the namespace App\Controllers\...
    $prefix = 'App\\Controllers\\';
    $base_dir = __DIR__ . '/src/Controllers/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// --- 3. Instantiate and Run the Telegram Controller ---
// All incoming webhook traffic is directed to the TelegramController.
use App\Controllers\TelegramController;

// Check if the bot token and the specific lottery channel ID are set before proceeding.
if (!isset($_ENV['TELEGRAM_BOT_TOKEN']) || !isset($_ENV['LOTTERY_CHANNEL_ID'])) {
    // Use error_log for server-side logging. Avoid echoing sensitive details.
    error_log('FATAL: TELEGRAM_BOT_TOKEN or LOTTERY_CHANNEL_ID are not set in the .env file.');
    // Send a generic, non-informative error response to the public.
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error.']);
    exit; // Stop execution if the bot is not configured.
}

// All good, let's handle the webhook request.
// Add a top-level log to confirm the webhook is being triggered.
error_log("--- [INFO] Telegram webhook received ---");
$input = file_get_contents('php://input');
error_log("Raw input: " . $input); // Log the raw payload for inspection.

try {
    $update = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Invalid JSON received: " . json_last_error_msg());
    }

    $controller = new TelegramController();
    $controller->handleWebhook($update); // Pass the decoded update to the controller.
    
    // Respond to Telegram to acknowledge receipt of the update.
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed.']);

} catch (\Exception $e) {
    // Log any uncaught exceptions from the controller logic.
    error_log('Error in Telegram webhook handler: ' . $e->getMessage());
    // Send a generic error response.
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while processing the request.']);
}
