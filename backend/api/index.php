<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// --- Autoloader ---
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
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

// --- Routing ---
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Simple router
$routes = [
    '/api/register' => ['controller' => 'App\\Controllers\\UserController', 'method' => 'register'],
    '/api/login' => ['controller' => 'App\\Controllers\\UserController', 'method' => 'login'],
    '/api/logout' => ['controller' => 'App\\Controllers\\UserController', 'method' => 'logout'],
    '/api/check-auth' => ['controller' => 'App\\Controllers\\UserController', 'method' => 'checkAuth'],
    '/api/lottery-results' => ['controller' => 'App\\Controllers\\LotteryController', 'method' => 'getLatestResults'],
];

if (isset($routes[$path])) {
    $controllerName = $routes[$path]['controller'];
    $methodName = $routes[$path]['method'];

    if (class_exists($controllerName)) {
        $controller = new $controllerName();
        if (method_exists($controller, $methodName)) {
            $controller->$methodName();
        } else {
            (new App\Controllers\BaseController())->jsonResponse(500, ['status' => 'error', 'message' => "Method {$methodName} not found."]);
        }
    } else {
        (new App\Controllers\BaseController())->jsonResponse(500, ['status' => 'error', 'message' => "Controller {$controllerName} not found."]);
    }
} else {
    // 404 Not Found
    (new App\Controllers\BaseController())->jsonResponse(404, ['status' => 'error', 'message' => 'Endpoint not found.']);
}
