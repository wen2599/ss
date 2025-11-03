<?php
header("Content-Type: application/json");

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        break;
    case 'login':
        break;
    case 'get_numbers':
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;
}
?>