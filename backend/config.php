<?php
// config.php

/**
 * 加载 .env 文件到环境变量中。
 * 增强版，以适应不同服务器环境。
 */
function load_env() {
    // 路径现在是正确的，因为 config.php 就在根目录
    $env_path = __DIR__ . '/.env';

    if (!is_readable($env_path)) {
        // Throw an exception instead of dying to allow for graceful error handling.
        throw new RuntimeException("FATAL ERROR in config.php: Cannot read .env file at path: " . htmlspecialchars($env_path));
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        // Throw an exception for failed read operations.
        throw new RuntimeException("FATAL ERROR in config.php: Failed to read content from .env file.");
    }

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 去除值两边的引号
            if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                $value = substr($value, 1, -1);
            }

            // 关键：同时设置到多个地方，增加兼容性
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// 立即执行加载函数
load_env();

/**
 * 增加一个全局函数来获取环境变量，优先从 $_ENV 或 $_SERVER 读取
 * 这样就不再完全依赖 getenv()
 */
function get_env_variable($key, $default = null) {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
?>