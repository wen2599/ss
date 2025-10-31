<?php
// backend/env_loader.php - WITH DEBUGGING

// 防止重复加载
if (!function_exists('load_env')) {
    function load_env() {
        // 如果环境变量已加载，则跳过
        if (!empty($_ENV['DB_HOST'])) {
            return;
        }

        $env_path = __DIR__ . '/../.env';
        echo "[DEBUG] Looking for .env file at: {$env_path}\n";

        if (!file_exists($env_path)) {
            echo "[DEBUG] .env file NOT FOUND.\n";
            http_response_code(500);
            // Since this might be run from CLI, also output to stderr
            fwrite(STDERR, "Error: .env file not found at the expected path.\n");
            die(json_encode(['error' => 'Internal server configuration error.']));
        }
        echo "[DEBUG] .env file found.\n";

        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            echo "[DEBUG] Failed to read the .env file.\n";
            fwrite(STDERR, "Error: Unable to read the .env file.\n");
            die(json_encode(['error' => 'Internal server configuration error.']));
        }

        echo "[DEBUG] Reading lines from .env file...\n";
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // 移除可能存在于值两边的引号
            if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            echo "[DEBUG] Loaded: {$name} = {$value}\n";
        }
        echo "[DEBUG] Finished reading .env file.\n";
    }
}
load_env();
