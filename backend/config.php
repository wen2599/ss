<?php
// 文件名: config.php
// 路径: 项目根目录

require_once __DIR__ . '/core/dotenv.php';

$dotenvPath = __DIR__ . '/../.env'; 

if (!file_exists($dotenvPath) || !is_readable($dotenvPath)) {
    $errorMessage = "FATAL ERROR: .env file not found or not readable at " . $dotenvPath;
    error_log($errorMessage); // 记录到服务器日志
    if (php_sapi_name() === 'cli') {
        die($errorMessage . "\n");
    }
    http_response_code(500);
    exit('Server Configuration Error.');
}

DotEnv::load($dotenvPath);

// --- 定义常量 ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');

define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
define('TELEGRAM_ADMIN_ID', $_ENV['TELEGRAM_ADMIN_ID'] ?? '');

define('JWT_SECRET', $_ENV['JWT_SECRET_KEY'] ?? 'your-default-jwt-secret');
define('JWT_EXPIRATION_TIME', 86400 * 7); // 7天

define('TELEGRAM_WEBHOOK_SECRET', $_ENV['TELEGRAM_SECRET_TOKEN'] ?? '');
define('EMAIL_WEBHOOK_SECRET', $_ENV['EMAIL_HANDLER_SECRET'] ?? '');

define('FRONTEND_URL', $_ENV['FRONTEND_URL'] ?? 'http://localhost');

define('CLOUDFLARE_ACCOUNT_ID', $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? '');
define('CLOUDFLARE_API_TOKEN', $_ENV['CLOUDFLARE_API_TOKEN'] ?? '');
define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY'] ?? '');