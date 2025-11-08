<?php
// File: backend/config.php (Final Robust Parser Version)

if (!function_exists('config')) {
    /**
     * 获取配置项的全局辅助函数。
     * 首次调用时会加载 .env 文件。
     * @param string $key The configuration key
     * @param mixed $default The default value if the key is not found
     * @return mixed
     */
    function config(string $key, $default = null) {
        static $config = null;

        if ($config === null) {
            $config = [];
            $envPath = __DIR__ . '/.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    foreach ($lines as $line) {
                        // 忽略纯注释行或空行
                        if (strpos(trim($line), ';') === 0 || trim($line) === '') {
                            continue;
                        }
                        
                        // 【关键修复】如果行内包含注释，则只取注释前的内容
                        if (strpos($line, ';') !== false) {
                            $line = substr($line, 0, strpos($line, ';'));
                        }

                        // 确保处理后仍然包含 '='
                        if (strpos($line, '=') !== false) {
                            list($name, $value) = explode('=', $line, 2);
                            $name = trim($name);
                            $value = trim(trim($value), "\"'"); // 先 trim 掉空格，再 trim 掉引号
                            
                            $config[$name] = $value;
                        }
                    }
                }
            }
        }
        
        return $config[$key] ?? $default;
    }
}

// --- Error Handling ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);