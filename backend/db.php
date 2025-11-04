<?php
// db.php
function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        $envFile = __DIR__ . '/.env';
        if (!file_exists($envFile)) {
            http_response_code(500);
            echo json_encode(['error' => 'Server configuration error.']);
            exit;
        }
        $env = parse_ini_file($envFile);

        $conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);

        if ($conn->connect_error) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
            exit;
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}
?>