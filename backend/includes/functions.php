<?php
// 确保这个函数只被定义一次
if (!function_exists('load_env')) {
    function load_env() {
        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) {
            // 在API上下文中，不要用die()，而是记录错误
            error_log('.env file not found at ' . $envPath);
            return;
        }
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim(trim($value), "\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// 确保这个函数只被定义一次
if (!function_exists('send_json_response')) {
    function send_json_response($data, $statusCode = 200) {
        // 在发送任何输出前清除可能存在的意外输出
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}

// 确保这个函数只被定义一次
if (!function_exists('verify_internal_secret')) {
    function verify_internal_secret() {
        load_env(); // 确保环境变量已加载
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $secret = getenv('INTERNAL_API_SECRET');
        if (!$secret) {
            send_json_response(['error' => 'Server configuration error: INTERNAL_API_SECRET not set'], 500);
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            if (hash_equals($secret, $matches[1])) { // 使用 hash_equals 防止时序攻击
                return true;
            }
        }
        send_json_response(['error' => 'Unauthorized'], 401);
    }
}
