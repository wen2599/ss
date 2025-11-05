<?php
// 启用错误日志，将错误输出到文件而不是浏览器
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../api-error.log'); // 为API设置独立的日志文件
error_reporting(E_ALL);

// --- 关键顺序：先加载包含函数的依赖文件 ---
require_once __DIR__ . '/../includes/functions.php';

// 现在可以安全地调用 load_env()
load_env();

// --- 再加载其他依赖 ---
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/EmailController.php';
require_once __DIR__ . '/../controllers/WinningNumbersController.php';

// 处理CORS预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit(0);
}
// 为实际请求设置CORS头
header("Access-Control-Allow-Origin: *");

// --- 路由逻辑 ---

// 从请求URI中解析出干净的路径
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$requestUri = $_SERVER['REQUEST_URI'];
$queryString = $_SERVER['QUERY_STRING'];
$route = str_replace('?' . $queryString, '', $requestUri);
if (strpos($route, $basePath) === 0) {
    $route = substr($route, strlen($basePath));
}
$route = trim($route, '/');

$requestParts = explode('/', $route);

$resource = $requestParts[0] ?? null;
$action = $requestParts[1] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($resource) {
        case 'users':
            $authController = new AuthController();
            if ($method === 'POST' && $action === 'register') {
                $authController->register($input);
            } elseif ($method === 'POST' && $action === 'login') {
                $authController->login($input);
            }
            break;

        case 'emails':
            verify_internal_secret();
            $emailController = new EmailController();
            if ($method === 'POST' && $action === 'receive') {
                $emailController->receive($input);
            }
            break;
        
        case 'winning-numbers':
            $winningNumbersController = new WinningNumbersController();
            if ($method === 'GET') {
                $winningNumbersController->getAll();
            } 
            elseif ($method === 'POST') {
                verify_internal_secret();
                $winningNumbersController->add($input);
            }
            break;
            
        default:
            send_json_response(['message' => 'Welcome to the API. Endpoint not found.'], 404);
            break;
    }
} catch (Throwable $e) {
    // 捕获任何未预料的错误，并以JSON格式返回，而不是让服务器崩溃
    error_log($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    send_json_response(['error' => 'An internal server error occurred.'], 500);
}
