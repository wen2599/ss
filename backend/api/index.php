<?php
// 允许来自前端的跨域请求 (主要由 Cloudflare Worker 处理，这里是备用)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Auth.php';

// 引入所有控制器
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/EmailController.php';
require_once __DIR__ . '/../controllers/WinningNumbersController.php';
// ... 其他控制器

$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$requestParts = explode('/', $requestUri);
$api_prefix = array_shift($requestParts); // 应该是 'api'

if (empty($requestParts)) {
    send_json_response(['message' => 'Welcome to the API'], 200);
}

$resource = $requestParts[0] ?? null;
$action = $requestParts[1] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$authController = new AuthController();
$emailController = new EmailController();
$winningNumbersController = new WinningNumbersController();

// 路由分发
switch ($resource) {
    case 'users':
        if ($method === 'POST' && $action === 'register') {
            $authController->register($input);
        } elseif ($method === 'POST' && $action === 'login') {
            $authController->login($input);
        }
        break;

    case 'emails':
        // 这个接口由 Cloudflare Worker 调用，使用内部密钥验证
        if ($method === 'POST' && $action === 'receive') {
            verify_internal_secret();
            $emailController->receive($input);
        }
        break;
    
    case 'winning-numbers':
        // 获取历史开奖号码 (公开或需要用户登录)
        if ($method === 'GET') {
            // 如果需要登录才能看，则在这里调用 Auth::verifyToken()
            $winningNumbersController->getAll();
        } 
        // 这个接口由 Telegram Bot 调用，使用内部密钥验证
        elseif ($method === 'POST') {
            verify_internal_secret();
            $winningNumbersController->add($input);
        }
        break;
        
    // ... 其他路由

    default:
        send_json_response(['error' => 'Not Found'], 404);
        break;
}
