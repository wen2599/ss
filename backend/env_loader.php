<?php
// 防止重复加载
if (!function_exists('load_env')) {
    function load_env() {
        // 如果环境变量已加载，则跳过
        if (!empty($_ENV['DB_HOST'])) {
            return;
        }
        $env_path = __DIR__ . '/../../.env'; // .env 文件在 backend 目录的上两级
        if (!file_exists($env_path)) {
            // 在 Web 环境下，不要暴露路径
            http_response_code(500);
            die(json_encode(['error' => 'Internal server configuration error.']));
        }
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // 移除可能存在于值两边的引号
            if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                $value = substr($value, 1, -1);
            }
            $_ENV[$name] = $value;
        }
    }
}
load_env();
