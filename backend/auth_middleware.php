<?php
// backend/auth_middleware.php

if (!function_exists('authenticate_user')) {
    /**
     * Authenticates a user based on a Bearer token from the Authorization header.
     *
     * @param mysqli $conn The database connection object.
     * @return array|null The user's data (id, email) if the token is valid, otherwise null.
     */
    function authenticate_user($conn) {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? '';

        if (preg_match('/Bearer\s(.+)/', $auth_header, $matches)) {
            $token = $matches[1];

            $stmt = $conn->prepare(
                "SELECT u.id, u.email FROM tokens t " .
                "JOIN users u ON t.user_id = u.id " .
                "WHERE t.token = ? AND t.expires_at > NOW()"
            );

            if ($stmt) {
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user) {
                    return $user; // Authentication successful
                }
            } else {
                // Log error if statement preparation fails
                error_log("Auth Middleware: Failed to prepare statement: " . $conn->error);
            }
        }

        // If token is missing, invalid, or expired
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Access is denied due to invalid credentials.']);
        return null;
    }
}
