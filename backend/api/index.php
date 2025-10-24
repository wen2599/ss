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

// --- Final Isolation Test ---
header('Content-Type: application/json');

// We directly test if BaseController can be loaded.
if (class_exists('App\Controllers\BaseController')) {
    echo json_encode([
        'status' => 'ok',
        'message' => 'SUCCESS: App\\Controllers\\BaseController loaded successfully.'
    ]);
} else {
    // This part will likely not be reached if there's a fatal error, 
    // but we include it for completeness.
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'FAILURE: class_exists() returned false for App\\Controllers\\BaseController.'
    ]);
}
