<?php
// backend/api/get_emails.php
// Version 2.0: Refactored for centralized routing and token authentication.

// This script is designed to be called by api_router.php, which handles:
// - Error reporting, JSON headers, and DB connection ($conn).
// - It also requires a valid user session, which we'll verify via a shared auth function.

require_once __DIR__ . '/../auth_middleware.php';

// Ensure $conn is available from the router context.
if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Router configuration error: Database connection not available.']);
    exit;
}

// --- Authentication Check ---
// The `authenticate_user` function is defined in `auth_middleware.php`
// It returns the user data array on success, or null on failure.
$user = authenticate_user($conn);
if (!$user) {
    // The middleware already sent the 401 response, so we just exit.
    exit;
}
$user_id = $user['id'];

// --- Main Logic ---
try {
    $emails = [];
    $sql = "SELECT id, from_address, subject, received_at FROM emails WHERE user_id = ? ORDER BY received_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Failed to prepare the SQL statement: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception("Database query failed: " . $stmt->error);
    }

    while($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }

    $stmt->close();
    echo json_encode(['success' => true, 'data' => $emails]);

} catch (Exception $e) {
    error_log("API Error in get_emails.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred while fetching emails.']);
}

// The connection will be closed by api_router.php
