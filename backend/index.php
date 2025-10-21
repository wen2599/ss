<?php
// backend/index.php
// Main router for all API requests.

require_once __DIR__ . '/bootstrap.php';

// --- Request Routing ---
$request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$request_method = $_SERVER['REQUEST_METHOD'];

// If the request path starts with 'backend/', remove it.
// This makes the routing logic independent of whether the URL includes the subdirectory.
if (strpos($request_path, 'backend/') === 0) {
    $request_path = substr($request_path, strlen('backend/'));
}

$path_parts = explode('/', $request_path);
// The endpoint is now the first part of the path after potentially removing 'backend/'
$endpoint = $path_parts[0] ?? null;

// Route the request to the appropriate handler.
switch ($endpoint) {
    case 'register':
        require_once __DIR__ . '/register_user.php';
        break;
    case 'login':
        require_once __DIR__ . '/login_user.php';
        break;
    case 'logout':
        require_once __DIR__ . '/logout_user.php';
        break;
    case 'check_session':
        require_once __DIR__ . '/check_session.php';
        break;
    case 'get_bills':
        require_once __DIR__ . '/get_bills.php';
        break;
    case 'delete_bill':
        require_once __DIR__ . '/delete_bill.php';
        break;
    case 'get_lottery_results':
        require_once __DIR__ . '/get_lottery_results.php';
        break;
    case 'telegram_webhook':
        require_once __DIR__ . '/telegram_webhook.php';
        break;
    case 'email_webhook':
        require_once __DIR__ . '/get_emails.php';
        break;
    default:
        // Check for admin actions if no other endpoint matches
        if ($endpoint === 'admin' && isset($path_parts[1])) {
            $admin_action = $path_parts[1];
            // Example protection: ?secret=YOUR_ADMIN_SECRET
            if (!isset($_GET['secret']) || $_GET['secret'] !== ($_ENV['ADMIN_SECRET'] ?? 'default_secret')) {
                 http_response_code(403);
                 die('Forbidden');
            }

            require_once __DIR__ . '/telegram_helpers.php';
            header('Content-Type: application/json');
            
            switch ($admin_action) {
                case 'set_telegram_webhook':
                    $webhookUrl = rtrim($_ENV['BACKEND_PUBLIC_URL'], '/') . '/telegram_webhook';
                    $secretToken = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
                    $result = setTelegramWebhook($webhookUrl, $secretToken);
                    echo json_encode($result);
                    break;
                case 'delete_telegram_webhook':
                    $result = deleteTelegramWebhook();
                    echo json_encode($result);
                    break;
                case 'get_webhook_info':
                    $result = getTelegramWebhookInfo();
                    echo json_encode($result);
                    break;
                default:
                    http_response_code(404);
                    echo json_encode(['error' => 'Admin action not found.']);
                    break;
            }
        } else {
            // --- Default case for unknown routes ---
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found.', 'requested' => $request_path]);
        }
        break;
}
