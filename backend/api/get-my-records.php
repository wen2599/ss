<?php
require_once __DIR__ . '/../bootstrap.php';

$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? null;

if ($auth_header) {
    list($type, $token) = explode(" ", $auth_header, 2);
    if (strcasecmp($type, "Bearer") == 0) {
        $user_id = verify_jwt($token);
        if ($user_id) {
            // Fetch records for the user
            global $db_connection;
            $stmt = $db_connection->prepare("SELECT id, record_name, record_value FROM records WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $records = $result->fetch_all(MYSQLI_ASSOC);

            http_response_code(200);
            echo json_encode($records);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid or expired token"]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Unsupported authentication type"]);
    }
} else {
    http_response_code(401);
    echo json_encode(["message" => "Authentication token not provided"]);
}
