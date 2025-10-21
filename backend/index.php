<?php
// backend/index.php
// Main router for all API requests.

require_once __DIR__ . '/bootstrap.php';

// --- Safe File Inclusion Function ---
// This function checks if a file exists before including it.
// If the file does not exist, it throws an exception that will be caught
// by our global exception handler, ensuring a clean JSON error response.
function safe_require_once(string $file): void {
    if (file_exists($file)) {
        require_once $file;
    } else {
        // This exception will be caught by the bootstrap's global exception handler.
        throw new RuntimeException("API handler file not found for the requested endpoint.");
    }
}

// --- Request Routing ---
$request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// If the request path starts with 'backend/', remove it for consistent routing.
if (strpos($request_path, 'backend/') === 0) {
    $request_path = substr($request_path, strlen('backend/'));
}

$path_parts = explode('/', $request_path);
$endpoint = $path_parts[0] ?? '';

// --- API Endpoint Mapping ---
// A clear mapping of endpoints to their handler files.
$api_routes = [
    'register' => 'register_user.php',
    'login' => 'login_user.php',
    'logout' => 'logout_user.php',
    'check_session' => 'check_session.php',
    'get_bills' => 'get_bills.php',
    'delete_bill' => 'delete_bill.php',
    'get_lottery_results' => 'get_lottery_results.php',
    'telegram_webhook' => 'telegram_webhook.php',
    'email_webhook' => 'get_emails.php',
];

// --- Route Handling ---
if (isset($api_routes[$endpoint])) {
    safe_require_once(__DIR__ . '/' . $api_routes[$endpoint]);
} elseif ($endpoint === 'admin' && isset($path_parts[1])) {
    // --- Admin Action Handling ---
    $admin_action = $path_parts[1];

    // Protect admin routes with a secret token.
    if (!isset($_GET['secret']) || $_GET['secret'] !== ($_ENV['ADMIN_SECRET'] ?? '')) {
         http_response_code(403);
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret token.']);
         exit();
    }

    safe_require_once(__DIR__ . '/telegram_helpers.php');
    header('Content-Type: application/json');

    switch ($admin_action) {
        case 'set_telegram_webhook':
            $webhookUrl = rtrim($_ENV['BACKEND_PUBLIC_URL'], '/') . '/telegram_webhook';
            $secretToken = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
            echo json_encode(setTelegramWebhook($webhookUrl, $secretToken));
            break;
        case 'delete_telegram_webhook':
            echo json_encode(deleteTelegramWebhook());
            break;
        case 'get_webhook_info':
            echo json_encode(getTelegramWebhookInfo());
            break;
        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Admin action not found.']);
            break;
    }
} else {
    // --- Default 404 Not Found ---
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'API endpoint not found.', 'requested' => $request_path]);
}
