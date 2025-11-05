<?php
// 启用最严格的错误处理
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/api-final-error.log'); // 路径修正
error_reporting(E_ALL);

// 全局致命错误处理器
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        error_log("FATAL SHUTDOWN in api/index.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        echo json_encode(['error' => 'A critical error occurred on the API server.']);
        exit;
    }
});

// -- 1. 加载核心函数 --
$functions_path = dirname(__DIR__) . '/includes/functions.php'; // 路径修正
if (!file_exists($functions_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: functions.php not found.']);
    exit;
}
require_once $functions_path;

// -- 2. 加载环境变量 --
load_env();

// -- 3. 加载所有其他依赖 --
$baseDir = dirname(__DIR__); // 路径修正
$dependencies = [
    $baseDir . '/config/database.php',
    $baseDir . '/includes/Auth.php',
    $baseDir . '/controllers/AuthController.php',
    $baseDir . '/controllers/EmailController.php',
    $baseDir . '/controllers/WinningNumbersController.php'
];

foreach ($dependencies as $dep) {
    if (!file_exists($dep)) {
        send_json_response(['error' => 'Server misconfiguration: Missing file ' . basename($dep)], 500);
    }
    require_once $dep;
}

// -- 4. CORS 和路由逻辑 --
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit(0);
}
header("Access-Control-Allow-Origin: *");

$basePath = dirname($_SERVER['SCRIPT_NAME']);
$requestUri = $_SERVER['REQUEST_URI'];
$route = parse_url($requestUri, PHP_URL_PATH);
if ($basePath !== '/' && strpos($route, $basePath) === 0) { $route = substr($route, strlen($basePath)); }
$route = trim($route, '/');
// 从路由中移除 'api' 前缀
if (strpos($route, 'api/') === 0) {
    $route = substr($route, 4);
}
$requestParts = explode('/', $route);

$resource = $requestParts[0] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$action = $requestParts[1] ?? null;
$input = json_decode(file_get_contents('php://input'), true);

switch ($resource) {
    case 'winning-numbers':
        $controller = new WinningNumbersController();
        if ($method === 'GET') { $controller->getAll(); }
        // 可以在这里添加 POST 逻辑
        break;
    default:
        send_json_response(['message' => 'API endpoint not found.'], 404);
        break;
}
?>