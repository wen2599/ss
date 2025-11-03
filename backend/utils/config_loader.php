<?php
// backend/utils/config_loader.php

/**
 * 智能加载 .env 文件。
 * 它会按照优先级顺序在多个常见位置寻找 .env 文件，并加载第一个找到的。
 *
 * @return string|null 成功加载的 .env 文件的路径，如果都找不到则返回 null。
 */
function loadEnvSmart() {
    // 定义要搜索的路径列表 (按优先级排序)
    $paths = [
        __DIR__ . '/../.env',                      // 1.
        dirname(__DIR__, 2) . '/.env',             // 2.
        (getenv('HOME') ? getenv('HOME') . '/.env' : null) // 3.
    ];

    $foundPath = null;

    foreach ($paths as $path) {
        if ($path && file_exists($path)) {
            $foundPath = $path;
            break;
        }
    }

    if (!$foundPath) {
        // 在 Web 环境下静默失败，在 CLI 环境下输出错误
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, "错误: 在所有预设路径中都找不到 .env 文件。\n");
            fwrite(STDERR, "请确保 .env 文件存在于以下任一位置：\n");
            foreach ($paths as $p) { if ($p) fwrite(STDERR, " - {$p}\n"); }
        }
        // 对于 web 请求, 返回 false 让调用者处理
        return null;
    }

    // 仅在 CLI 模式下打印成功信息
    if (php_sapi_name() === 'cli') {
        echo "成功找到并加载配置文件: {$foundPath}\n";
    }

    $lines = file($foundPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim(trim($value), '"');
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return $foundPath;
}

// 自动执行加载
loadEnvSmart();
?>