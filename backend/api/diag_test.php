<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$response = ['status' => 'success', 'message' => 'PHP script executed.'];

try {
    // --- Load .env ---
    $envPath = __DIR__ . '/../../.env';
    if (!file_exists($envPath)) throw new Exception('.env file not found.');
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, '"');
    }
    $response['env_loaded'] = true;

    // --- Check PDO ---
    if (!class_exists('PDO')) throw new Exception('PDO class not found. pdo_mysql extension is missing.');
    $response['pdo_exists'] = true;

    // --- Test DB Connection ---
    $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $response['db_connection'] = 'successful';

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
