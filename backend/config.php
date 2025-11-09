<?php
// File: backend/config.php (Final Debugging Version for FRONTEND_URL)

// 定义一个调试日志文件路径
define('CONFIG_DEBUG_LOG', __DIR__ . '/config_debug.log');

// 在脚本执行时，如果日志已存在，则不清空，以便我们追加日志
// unlink(CONFIG_DEBUG_LOG); 

function _log_config_debug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, CONFIG_DEBUG_LOG);
}

// 记录哪个文件正在加载 config.php
$caller = debug_backtrace()[0]['file'];
_log_config_debug("--- config.php loaded by: {$caller} ---");

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        static $config = null;
        
        if ($config === null) {
            _log_config_debug("Static \$config is null. Initializing...");
            $config = [];
            $envPath = __DIR__ . '/.env';
            
            if (file_exists($envPath) && is_readable($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    foreach ($lines as $line) {
                        if (strpos(trim($line), ';') === 0) continue;
                        if (strpos($line, ';') !== false) {
                            $line = substr($line, 0, strpos($line, ';'));
                        }
                        if (strpos($line, '=') !== false) {
                            list($name, $value) = explode('=', $line, 2);
                            $name = trim($name);
                            $value = trim(trim($value), "\"'");
                            $config[$name] = $value;
                            if ($name === 'FRONTEND_URL') {
                                _log_config_debug("SUCCESS: Found and loaded [FRONTEND_URL] = [{$value}]");
                            }
                        }
                    }
                }
            } else {
                 _log_config_debug("FATAL: .env file not found or not readable.");
            }
        }

        $returnValue = $config[$key] ?? $default;
        _log_config_debug("config('{$key}') requested, returning: " . ($returnValue ?? 'NULL'));
        return $returnValue;
    }
}

// ... Error Handling ...
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);