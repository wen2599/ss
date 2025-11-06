<?php
// Loads configuration from .env file
if (!function_exists('load_config')) {
    function load_config() {
        $config = [];
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), ';') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    // Remove quotes if present
                    if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                        $value = substr($value, 1, -1);
                    }
                    $config[$key] = $value;
                }
            }
        }
        return $config;
    }
}
$config = load_config();
