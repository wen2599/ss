<?php
// backend/env_loader.php

if (!function_exists('load_env')) {
    function load_env() {
        if (!empty($_ENV['DB_HOST'])) {
            return;
        }
        $env_path = __DIR__ . '/../.env';
        if (!file_exists($env_path)) {
            http_response_code(500);
            die(json_encode(['error' => 'Internal server configuration error.']));
        }
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                $value = substr($value, 1, -1);
            }
            $_ENV[$name] = $value;
        }
    }
}
load_env();
