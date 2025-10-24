<?php
declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/bootstrap.php';

// --- Manual Class Loading ---
// This approach avoids potential issues with spl_autoload_register on shared hosting environments.
require_once __DIR__ . '/src/Controllers/BaseController.php';
require_once __DIR__ . '/src/Controllers/UserController.php';
require_once __DIR__ . '/src/Controllers/EmailController.php';
require_once __DIR__ . '/src/Controllers/LotteryController.php';
require_once __DIR__ . '/src/Controllers/TelegramController.php';

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
        '/telegram-webhook' => ['App\Controllers\TelegramController', 'webhook'],
    ],
];

$routeFound = false;
// Create a simple error handler using an anonymous class that extends BaseController
$errorController = new class extends \App\Controllers\BaseController {};

foreach ($routes[$requestMethod] ?? [] as $path => $handler) {
    $regex = '#^' . $path . '$#';
    if (preg_match($regex, $requestUri, $matches)) {
        array_shift($matches); // Remove the full match from the beginning of the array
        
        $routeFound = true;
        $controllerName = $handler[0];
        $methodName = $handler[1];

        // Since we manually included all files, the class should always exist.
        $controller = new $controllerName();
        
        // Pass the captured URL parameters (e.g., email ID) to the method.
        call_user_func_array([$controller, $methodName], $matches);

        break; // Stop after finding the first matching route
    }
}

if (!$routeFound) {
    $errorController->jsonError(404, 'API endpoint not found.');
}
