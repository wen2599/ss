<?php

// --- Environment Variable Loading ---
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $dotenv = file_get_contents($dotenvPath);
    $lines = explode("\n", $dotenv);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
}


// --- Basic Routing ---
header('Content-Type: application/json'); // Set content type for all API responses

// Get the request path and endpoint from the URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$endpoint = $_GET['endpoint'] ?? null;

// Normalize the routing key
$route = $endpoint ? strtolower($endpoint) : strtolower($path);

// Simple router
switch ($route) {
    case '/api/status':
    case 'status':
        echo json_encode(['status' => 'ok', 'message' => 'API is running']);
        break;

    case '/api/db-check':
    case 'db-check':
        // --- Database Connection ---
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_DATABASE');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo json_encode(['status' => 'ok', 'message' => '数据库连接成功!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => '数据库连接失败: ' . $e->getMessage()]);
        }
        break;

    case '/api/telegram_webhook':
    case 'telegramwebhook':
        require __DIR__ . '/telegramWebhook.php';
        break;

    case '/api/email_handler':
    case 'email_handler':
        require __DIR__ . '/email_handler.php';
        break;
        
    case '/api/register':
    case 'register_user':
        require __DIR__ . '/register_user.php';
        break;
    
    case '/api/login':
    case 'login_user':
        require __DIR__ . '/login_user.php';
        break;

    case '/api/check_auth':
    case 'check_auth':
        require __DIR__ . '/check_auth.php';
        break;

    case '/api/get_emails':
    case 'get_emails':
        require __DIR__ . '/get_emails.php';
        break;

    default:
        // Handle 404 Not Found for any other API routes
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found: ' . $route]);
        break;
}
