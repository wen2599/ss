<?php
require_once __DIR__ . '/../helpers.php'; // For sendJsonResponse

class EmailController {
    private $db_connection;

    public function __construct($db_connection) {
        $this->db_connection = $db_connection;
    }

    /**
     * Handles the incoming request from the Cloudflare Worker.
     */
    public function handleRequest() {
        // Data is sent as FormData (POST), not JSON.
        $action = $_REQUEST['action'] ?? null;

        switch ($action) {
            case 'save_email':
                $this->saveEmail();
                break;
            case 'is_user_registered':
                $this->isUserRegistered();
                break;
            default:
                sendJsonResponse(400, ['success' => false, 'message' => 'Invalid action specified.']);
                break;
        }
    }

    /**
     * Verifies the worker secret from the request.
     * @return bool
     */
    private function verifySecret() {
        $worker_secret = getenv('WORKER_SECRET');
        $submitted_secret = $_REQUEST['worker_secret'] ?? null;
        
        if (!$worker_secret || $submitted_secret !== $worker_secret) {
            sendJsonResponse(403, ['success' => false, 'message' => 'Forbidden: Invalid secret.']);
            return false;
        }
        return true;
    }

    /**
     * Checks if a user is registered based on their email address.
     * This is called by the Worker before forwarding the email content.
     */
    private function isUserRegistered() {
        if (!$this->verifySecret()) return;

        $email = $_REQUEST['email'] ?? null;
        if (empty($email)) {
            sendJsonResponse(400, ['success' => false, 'message' => 'Email parameter is required.']);
            return;
        }

        $stmt = $this->db_connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            sendJsonResponse(200, ['success' => true, 'is_registered' => true]);
        } else {
            sendJsonResponse(200, ['success' => true, 'is_registered' => false]);
        }
        $stmt->close();
    }

    /**
     * Saves the email content forwarded from the Worker into the database.
     */
    private function saveEmail() {
        if (!$this->verifySecret()) return;

        // --- Extract data from POST request ---
        $from_address = $_POST['from_address'] ?? null;
        $to_address = $_POST['to_address'] ?? null;
        $subject = $_POST['subject'] ?? 'No Subject';
        $body = $_POST['body'] ?? '';
        $raw_email = $_POST['raw_email'] ?? '';

        if (empty($from_address) || empty($raw_email)) {
            sendJsonResponse(400, ['success' => false, 'message' => 'Missing required fields (from_address, raw_email).']);
            return;
        }

        // --- Find user_id from the sender's email ---
        $stmt = $this->db_connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $from_address);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $user_id = $user['id'];
            $stmt->close();

            // --- Insert the email into the database ---
            $insert_stmt = $this->db_connection->prepare(
                "INSERT INTO emails (user_id, from_address, to_address, subject, body, raw_email, status) 
                 VALUES (?, ?, ?, ?, ?, ?, 'received')"
            );
            $insert_stmt->bind_param("issssss", $user_id, $from_address, $to_address, $subject, $body, $raw_email);

            if ($insert_stmt->execute()) {
                sendJsonResponse(201, ['success' => true, 'message' => 'Email saved successfully.', 'email_id' => $insert_stmt->insert_id]);
            } else {
                error_log("Failed to save email for user_id {$user_id}: " . $insert_stmt->error);
                sendJsonResponse(500, ['success' => false, 'message' => 'Failed to save email.']);
            }
            $insert_stmt->close();
        } else {
            // This case should ideally not happen because the worker pre-verifies.
            // But as a safeguard, we handle it.
            $stmt->close();
            sendJsonResponse(404, ['success' => false, 'message' => 'User not found for the given email address.']);
        }
    }
}
