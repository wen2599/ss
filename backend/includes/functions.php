<?php
// 加载 .env 文件
function load_env() {
    echo "--- Debug: Starting .env load attempt ---\n";
    echo "--- Debug: Script directory (__DIR__): " . __DIR__ . "\n";

    $path_attempt1 = __DIR__ . '/../../.env';
    echo "--- Debug: Checking path 1 (project root): " . $path_attempt1 . "\n";

    $path_attempt2 = __DIR__ . '/../.env';
    echo "--- Debug: Checking path 2 (backend root): " . $path_attempt2 . "\n";

    $dotenv_path = null;

    if (file_exists($path_attempt1)) {
        echo "--- Debug: .env file found at path 1.\n";
        $dotenv_path = $path_attempt1;
    } elseif (file_exists($path_attempt2)) {
        echo "--- Debug: .env file found at path 2.\n";
        $dotenv_path = $path_attempt2;
    } else {
        echo "--- Debug: .env file not found in any checked paths.\n";
        return;
    }

    $lines = file($dotenv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 发送 JSON 响应
function send_json_response($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// 验证内部 API 密钥
function verify_internal_secret() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        if ($token === getenv('INTERNAL_API_SECRET')) {
            return true;
        }
    }
    send_json_response(['error' => 'Unauthorized'], 401);
}

load_env();
