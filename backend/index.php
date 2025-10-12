<?php

// --- Environment Variable Loading ---
$dotenv = file_get_contents(__DIR__ . '/../.env');
$lines = explode("\n", $dotenv);

foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
        continue;
    }

    list($name, $value) = explode('=', $line, 2);
    putenv(trim($name) . '=' . trim($value));
}

// --- Basic Routing ---
header('Content-Type: application/json'); // Set content type for all API responses

// Get the request path from the URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Simple router
switch ($path) {
    case '/api/status':
        echo json_encode(['status' => 'ok', 'message' => 'API is running']);
        break;

    case '/api/db-check':
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
        // Handle the Telegram webhook by including the dedicated script.
        require __DIR__ . '/telegramWebhook.php';
        break;

    case '/api/email_upload':
        // Handle the email upload by including the dedicated script.
        require __DIR__ . '/email_handler.php';
        break;
        
    default:
        // Handle 404 Not Found for any other API routes
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        break;
}
