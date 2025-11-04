<?php
// backend/config/load_env.php

function load_env($path) {
    if (!file_exists($path)) {
        // 在生产环境中，最好不要抛出异常，而是记录日志并优雅地失败
        error_log(".env file not found at " . $path);
        // 如果 .env 是必须的，可以 die() 或 throw new Exception()
        // throw new Exception(".env file not found at " . $path);
        return; 
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // 跳过注释行
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // 分割键和值
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // --- 关键修改：处理带引号的值 ---
        if (strlen($value) > 1) {
            // 检查值是否以引号开始和结束
            $firstChar = $value[0];
            $lastChar = $value[strlen($value) - 1];
            
            // 如果值的首尾是匹配的单引号或双引号，则移除它们
            if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        // --- 修改结束 ---

        // 设置环境变量
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 加载 .env 文件
// __DIR__ 指向当前文件(load_env.php)所在的目录，即 /config/
// 所以我们需要回到上一层目录 ../ 来找到 .env
try {
    load_env(__DIR__ . '/../.env');
} catch (Exception $e) {
    // 如果.env文件不存在或无法读取，这里可以处理错误
    // 在生产环境中，建议记录错误日志而不是直接输出
    error_log('Error loading .env file: ' . $e->getMessage());
    // 如果.env是关键的，可能需要终止脚本
    // die('Could not load application configuration.');
}