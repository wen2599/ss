<?php
declare(strict_types=1);

// --- Autoloader ---
require_once __DIR__ . '/vendor/autoload.php';

use App\Controllers\EmailController;
use App\Controllers\LotteryController;
use App\Controllers\UserController;

// --- AGGRESSIVE CORS FIX for shared hosting ---
if (isset($_SERVER['REQUEST_METHOD'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = ['https://ss.wenxiuxiu.eu.org', 'http://localhost:5173'];
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-control-allow-headers: Authorization, Content-Type, X-Requested-With, X-Worker-Secret, Accept, Origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// --- Global Error Handling ---
set_exception_handler(function (Throwable $e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred on the server.']);
    exit;
});
// ... (other error handlers remain the same) ...

// --- Database Connection ---
require_once __DIR__ . '/database/migration.php';
$pdo = getDbConnection();

// --- Controller Instantiation ---
$userController = new UserController($pdo);
$emailController = new EmailController($pdo);
$lotteryController = new LotteryController($pdo);

// --- Simple Router ---
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];
$basePath = '/api';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// --- Helper Functions ---
function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
function jsonError(int $statusCode, string $message, array $details = []): void
{
    jsonResponse($statusCode, array_merge(['status' => 'error', 'message' => $message], $details));
}

// --- Route Definitions ---
switch ($requestUri) {
    case '/ping':
        if ($requestMethod === 'GET') {
            jsonResponse(200, ['status' => 'success', 'data' => 'Backend is running (Pure PHP, Refactored)']);
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/register':
        if ($requestMethod === 'POST') {
            $userController->register();
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/login':
        if ($requestMethod === 'POST') {
            $userController->login();
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/logout':
        if ($requestMethod === 'POST') {
            $userController->logout();
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/users/is-registered':
        if ($requestMethod === 'GET') {
            $userController->isUserRegistered();
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/emails':
        if ($requestMethod === 'POST') {
            $emailController->receive();
        } elseif ($requestMethod === 'GET') {
            $emailController->list();
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case (preg_match('/^\/emails\/(\d+)$/', $requestUri, $matches) ? true : false):
        if ($requestMethod === 'GET') {
            $emailController->get($matches[1]);
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;
    
    case '/lottery-results':
        if ($requestMethod === 'GET') {
            $lotteryController->getResults();
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    default:
        jsonError(404, 'Not Found');
        break;
}
