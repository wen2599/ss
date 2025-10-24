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

// --- Simple Router ---
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];
$basePath = '/api';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// --- Route Definitions ---
$routes = [
    'GET' => [
        '/ping' => ['App\Controllers\UserController', 'ping'],
        '/users/is-registered' => ['App\Controllers\UserController', 'isRegistered'],
        '/emails' => ['App\Controllers\EmailController', 'list'],
        '/emails/(\d+)' => ['App\Controllers\EmailController', 'get'],
        '/lottery-results' => ['App\Controllers\LotteryController', 'getResults'],
    ],
    'POST' => [
        '/register' => ['App\Controllers\UserController', 'register'],
        '/login' => ['App\Controllers\UserController', 'login'],
        '/logout' => ['App\Controllers\UserController', 'logout'],
        '/emails' => ['App\Controllers\EmailController', 'receive'],
    ],
];

$routeFound = false;
$errorController = new class extends \App\Controllers\BaseController {};

foreach ($routes[$requestMethod] ?? [] as $path => $handler) {
    if (preg_match('#^' . $path . '$#', $requestUri, $matches)) {
        $routeFound = true;
        $controllerName = $handler[0];
        $methodName = $handler[1];
        
        // --- DEBUGGING STEP ---
        // Check if the class exists, which triggers the autoloader.
        // But do not instantiate it yet.
        if (class_exists($controllerName)) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'ok', 
                'message' => 'Route found and controller class exists.',
                'controller' => $controllerName,
                'method' => $methodName
            ]);
        } else {
            $errorController->jsonError(500, "DEBUG: Controller class {$controllerName} not found by autoloader.");
        }
        // --- END DEBUGGING STEP ---

        break; // Stop after finding the first route
    }
}

if (!$routeFound) {
    $errorController->jsonError(404, 'Not Found');
}
