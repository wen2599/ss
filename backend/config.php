<?php
// backend/config.php

/**
 * 加载 .env 文件到环境变量中
 * 这段代码是手动实现的，以避免使用任何依赖库。
 */
function load_env() {
    $env_path = __DIR__ . '/.env';
    if (!is_readable($env_path)) {
        // 如果 .env 文件不存在或不可读，则记录错误并退出
        error_log(".env file not found or not readable at: " . $env_path);
        // 您可以根据需要决定是否要在这里终止脚本
        // die(".env file not found.");
        return;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 忽略注释行
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // 去除值两边的引号（如果存在）
        if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
            $value = substr($value, 1, -1);
        }

        // 设置到环境变量中
        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// 执行加载函数
load_env();
?>