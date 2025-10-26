<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers.php'; // Ensure helpers are included for new functions

// --- Simple Request Router ---
$route = $_GET['route'] ?? null;
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Fallback for direct file access (legacy endpoints) ---
if ($route === null) {
    $script_name = basename($_SERVER['SCRIPT_NAME']);
    $file_path = __DIR__ . '/api/' . $script_name;
    if (file_exists($file_path) && is_file($file_path)) {
        require_once $file_path;
        exit;
    }
}

// --- New Controller-based Routing ---
switch ($route) {
    case 'auth':
        if ($request_method === 'POST') {
            require_once __DIR__ . '/api/AuthController.php';
            $controller = new AuthController();
            $controller->handleRequest();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'users/is-registered':
        if ($request_method === 'GET') {
            require_once __DIR__ . '/api/UserController.php';
            $controller = new UserController();
            $controller->isRegistered();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'emails':
        if ($request_method === 'POST') {
            require_once __DIR__ . '/api/EmailController.php';
            $controller = new EmailController();
            $controller->handleRequest();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case 'lottery-draws': // New route for all lottery draws
        if ($request_method === 'GET') {
            header("Content-Type: application/json; charset=UTF-8");
            global $db_connection;
            $query = "SELECT draw_date, lottery_type, draw_period, numbers, created_at FROM lottery_draws ORDER BY draw_date DESC, draw_period DESC";
            $response = [];
            try {
                if ($result = $db_connection->query($query)) {
                    $draws = $result->fetch_all(MYSQLI_ASSOC);
                    if (!empty($draws)) {
                        http_response_code(200);
                        $response = [
                            'status' => 'success',
                            'data' => $draws
                        ];
                    } else {
                        http_response_code(404);
                        $response = [
                            'status' => 'error',
                            'message' => 'No lottery records found.'
                        ];
                    }
                    $result->free();
                } else {
                    http_response_code(500);
                    $response = [
                        'status' => 'error',
                        'message' => 'Database query failed.'
                    ];
                    error_log("DB Error in lottery-draws route: " . $db_connection->error);
                }
            } catch (Exception $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'An unexpected server error occurred.'
                ];
                error_log("Exception in lottery-draws route: " . $e->getMessage());
            }
            echo json_encode($response);
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
