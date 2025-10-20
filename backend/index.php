<?php
// backend/index.php
// Main router for all API requests.

require_once __DIR__ . '/bootstrap.php';

// --- Request Routing ---
$request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$request_method = $_SERVER['REQUEST_METHOD'];

$path_parts = explode('/', $request_path);
$endpoint = $path_parts[0] ?? null;

// Route the request to the appropriate handler.
switch ($endpoint) {
    // --- API for Frontend ---
    case 'api':
        $api_endpoint = $path_parts[1] ?? null;
        switch ($api_endpoint) {
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
            default:
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['error' => 'API endpoint not found.']);
                break;
        }
        break;

    // --- Webhook for Telegram Bot ---
    case 'telegram_webhook':
        require_once __DIR__ . '/telegram_webhook.php';
        break;
        
    // --- Webhook for Email Worker ---
    case 'email_webhook':
        require_once __DIR__ . '/get_emails.php';
        break;

    // --- Admin/Management Endpoints ---
    // NOTE: These should be protected (e.g., by IP whitelist or a secret key in the URL)
    case 'admin':
        $admin_action = $path_parts[1] ?? null;
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
        break;

    // --- Default case for unknown routes ---
    default:
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'message' => 'The requested endpoint does not exist.']);
        break;
}
