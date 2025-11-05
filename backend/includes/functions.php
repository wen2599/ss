<?php
// 加载 .env 文件
function load_env() {
    $dotenv_path = __DIR__ . '/../../.env';
    if (!file_exists($dotenv_path)) {
        // 如果在根目录找不到，尝试在当前目录的上一级目录找（兼容旧的部署方式）
        $dotenv_path = __DIR__ . '/../.env';
        if (!file_exists($dotenv_path)) {
            return;
        }
    }
    $lines = file($dotenv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Strip surrounding quotes from value
        if (strlen($value) > 1 && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

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
