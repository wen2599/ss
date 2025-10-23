<?php
declare(strict_types=1);

// --- AGGRESSIVE CORS FIX for shared hosting --- (Place at very top of entry file)
if (isset($_SERVER['REQUEST_METHOD'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://ss.wenxiuxiu.eu.org',
        'http://localhost:5173' // for local development
    ];

    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-Worker-Secret, Accept, Origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        // Respond with 204 No Content for preflight requests
        http_response_code(204);
        exit;
    }
}
// --- END AGGRESSIVE CORS FIX ---


// --- Global Error Handling ---
set_exception_handler(function (Throwable $e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred on the server.',
        // 'details' => $e->getMessage() // Uncomment for debugging, but hide in production
    ]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $lastError = error_get_last();
    if ($lastError && ($lastError['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))) {
        // If a fatal error occurred that wasn't caught by the exception handler
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'A fatal error occurred on the server.',
            // 'details' => $lastError['message'] // Uncomment for debugging, but hide in production
        ]);
    }
});

// --- Conditionally Include Database and Run Migrations ---
// We wrap this in a check to ensure that lightweight preflight OPTIONS requests
// don't needlessly try to connect to the database, which is a common source of failure.
if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    require_once __DIR__ . '/database/migration.php';
    runMigrations(getDbConnection());
}

// --- Simple Router ---
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// All API routes are expected to be relative to /api/
// Remove the /api prefix for internal routing
$basePath = '/api';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

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
            jsonResponse(200, ['status' => 'success', 'data' => 'Backend is running (Pure PHP)']);
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/register':
        if ($requestMethod === 'POST') {
            require __DIR__ . '/src/handlers/register.php';
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/login':
        if ($requestMethod === 'POST') {
            require __DIR__ . '/src/handlers/login.php';
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/logout':
        if ($requestMethod === 'POST') {
            require __DIR__ . '/src/handlers/logout.php';
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/users/is-registered':
        if ($requestMethod === 'GET') {
            require __DIR__ . '/src/handlers/is_user_registered.php';
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case '/emails':
        if ($requestMethod === 'POST') {
            require __DIR__ . '/src/handlers/receive_email.php';
        } else if ($requestMethod === 'GET') {
            require __DIR__ . '/src/handlers/list_emails.php';
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    case (preg_match('/^\/emails\/(\d+)$/', $requestUri, $matches) ? true : false):
        if ($requestMethod === 'GET') {
            $_GET['id'] = $matches[1]; // Pass ID as GET param for handler
            require __DIR__ . '/src/handlers/get_email.php';
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;
    
    case '/lottery-results':
        if ($requestMethod === 'GET') {
            require __DIR__ . '/src/handlers/lottery_results.php';
        } else {
            jsonError(405, 'Method Not Allowed');
        }
        break;

    default:
        jsonError(404, 'Not Found');
        break;
}
