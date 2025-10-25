<?php

declare(strict_types=1);

// backend/bootstrap.php

// --- CORS Configuration ---
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Environment and Database Initialization ---
require_once __DIR__ . '/load_env.php';

$db_connection = null;

function connect_to_database()
{
    global $db_connection;

    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');
    $db_name = getenv('DB_NAME');

    if (! $db_host || ! $db_user || ! $db_pass || ! $db_name) {
        http_response_code(500);
        echo json_encode(["message" => "Database configuration is incomplete."]);
        exit;
    }

    $db_connection = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($db_connection->connect_error) {
        http_response_code(500);
        echo json_encode(["message" => "Database connection failed: " . $db_connection->connect_error]);
        exit;
    }

    $db_connection->set_charset("utf8mb4");
}

// --- JWT Helper Functions ---
require_once __DIR__ . '/api/jwt_helper.php';

// --- Global Execution ---
connect_to_database();
