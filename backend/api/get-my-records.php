<?php

// --- CORS Configuration ---
// This section handles Cross-Origin Resource Sharing (CORS) preflight requests and sets headers.

// Allow requests from your specific frontend origin.
// IMPORTANT: Replace * with your actual frontend domain in production, e.g., 'https://ss.wenxiuxiu.eu.org'
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle the browser's preflight 'OPTIONS' request.
// This is crucial for CORS to work correctly with methods like POST.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Respond with a 204 No Content status, indicating success and that no further action is needed.
    http_response_code(204);
    // Stop script execution, as this was just a preflight check.
    exit;
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/jwt_helper.php'; // Ensure JWT helper is included

$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? null;

if ($auth_header) {
    list($type, $token) = explode(" ", $auth_header, 2);
    if (strcasecmp($type, "Bearer") == 0) {
        $user_id = verify_jwt($token);
        if ($user_id) {
            // Fetch records for the user from the 'emails' table
            global $db_connection;
            // Selecting relevant columns for the frontend
            $stmt = $db_connection->prepare("SELECT id, subject, from_address, body, received_at, extracted_data FROM emails WHERE user_id = ? ORDER BY received_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $emails = [];
            while ($row = $result->fetch_assoc()) {
                // Decode extracted_data if it's stored as JSON string
                if (isset($row['extracted_data'])) {
                    $row['extracted_data'] = json_decode($row['extracted_data'], true);
                }
                $emails[] = $row;
            }

            http_response_code(200);
            echo json_encode($emails);
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
