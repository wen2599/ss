<?php
// backend/api/get_email_details.php
// Version 2.0: Refactored for centralized routing and token authentication.

require_once __DIR__ . '/../auth_middleware.php';

// Ensure $conn is available from the router context.
if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Router configuration error: Database connection not available.']);
    exit;
}

// --- Authentication Check ---
$user = authenticate_user($conn);
if (!$user) {
    exit; // Middleware handles the response.
}
$user_id = $user['id'];

// --- Input Validation ---
$email_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$email_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing email ID.']);
    exit;
}

// --- Main Logic ---
try {
    // Security enhancement: Ensure the email belongs to the authenticated user.
    $stmt = $conn->prepare("SELECT id, from_address, subject, body_html, body_text, received_at FROM emails WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement for email details: " . $conn->error);
    }

    $stmt->bind_param("ii", $email_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $email = $result->fetch_assoc();
    $stmt->close();

    if ($email) {
        echo json_encode(['success' => true, 'data' => $email]);
    } else {
        // This is a 404 because the specific resource was not found for this user.
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Email not found or you do not have permission to view it.']);
    }

} catch (Exception $e) {
    error_log("API Error in get_email_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
}

// The connection will be closed by api_router.php
