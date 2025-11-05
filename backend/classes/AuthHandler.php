<?php

class AuthHandler {
    private $db;
    private $apiKey;

    public function __construct($db, $apiKey) {
        $this->db = $db;
        $this->apiKey = $apiKey;
    }

    public function validateAuthToken() {
        $authHeader = null;
        if (isset($_SERVER['Authorization'])) {
            $authHeader = $_SERVER['Authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
        }

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(array('success' => false, 'error' => 'Unauthorized: Missing or invalid token.'));
            exit;
        }

        $token = $matches[1];

        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE auth_token = :token AND token_expires_at > NOW()");
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch();

            if (!$user) {
                http_response_code(401);
                echo json_encode(array('success' => false, 'error' => 'Unauthorized: Invalid or expired token.'));
                exit;
            }

            return $user; // Return user data on success
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(array('success' => false, 'error' => 'Token validation failed: ' . $e->getMessage()));
            exit;
        }
    }

    public function validateApiKey() {
        $clientApiKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null;
        if ($clientApiKey !== $this->apiKey) {
            http_response_code(401);
            echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
            exit;
        }
    }
}
