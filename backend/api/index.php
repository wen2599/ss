<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// --- Autoloader ---
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
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
    '/api/emails' => ['controller' => 'App\\Controllers\\EmailController', 'method' => 'handleEmails'],
    '/api/users/is-registered' => ['controller' => 'App\\Controllers\\UserController', 'method' => 'isRegistered'],
];

// --- Error Handling for Routing ---
// Use an anonymous class to avoid "Cannot instantiate abstract class" errors
// when BaseController needs to be used for sending routing errors.
$error_handler = new class extends \App\Controllers\BaseController {
    public function send(int $code, string $message) {
        $this->jsonResponse(['status' => 'error', 'message' => $message], $code);
    }
};

if (isset($routes[$path])) {
    $controllerName = $routes[$path]['controller'];
    $methodName = $routes[$path]['method'];

    if (class_exists($controllerName)) {
        try {
            $controller = new $controllerName();
            if (method_exists($controller, $methodName)) {
                $controller->$methodName();
            } else {
                $error_handler->send(500, "Method {$methodName} not found in controller {$controllerName}.");
            }
        } catch (Error $e) {
            // Catch fatal errors, e.g., constructor issues
            error_log("Fatal error instantiating controller {$controllerName}: " . $e->getMessage());
            $error_handler->send(500, 'Server error during controller initialization.');
        }
    } else {
        $error_handler->send(500, "Controller class {$controllerName} not found.");
    }
} else {
    // Correctly handle 404 Not Found
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
    exit;
}
