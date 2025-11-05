<?php

class EmailHandler {
    private $db;
    private $workerSecret;

    public function __construct($db, $workerSecret) {
        $this->db = $db;
        $this->workerSecret = $workerSecret;
    }

    public function validateWorkerSecret() {
        $clientSecret = isset($_GET['worker_secret']) ? $_GET['worker_secret'] : (isset($_POST['worker_secret']) ? $_POST['worker_secret'] : null);
        if (!$this->workerSecret || $clientSecret !== $this->workerSecret) {
            http_response_code(401);
            echo json_encode(array('success' => false, 'error' => 'Unauthorized worker'));
            exit;
        }
    }

    public function isUserRegistered() {
        if (!isset($_GET['email'])) {
            return array('success' => false, 'error' => 'Email parameter is required.');
        }

        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute(array(':email' => $_GET['email']));
            $count = $stmt->fetchColumn();

            return array('success' => true, 'is_registered' => $count > 0);
        } catch (Exception $e) {
            http_response_code(500);
            return array('success' => false, 'error' => 'Database query failed: ' . $e->getMessage());
        }
    }

    public function processEmail() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return array('success' => false, 'error' => 'Method Not Allowed');
        }

        $from = isset($_POST['from']) ? $_POST['from'] : null;
        $subject = isset($_POST['subject']) ? $_POST['subject'] : null;
        $body = isset($_POST['body']) ? $_POST['body'] : null;

        if (!$from || !$subject || !$body) {
            return array('success' => false, 'error' => 'Missing required fields: from, subject, body.');
        }

        try {
            $pdo = $this->db->getConnection();

            // Find the user_id from the sender's email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(array(':email' => $from));
            $user = $stmt->fetch();

            if (!$user) {
                // This should theoretically not happen if the worker checks first, but as a safeguard:
                return array('success' => false, 'error' => 'Sender not found.');
            }
            $userId = $user['id'];

            // Insert the email into the database
            $sql = "INSERT INTO emails (user_id, sender, subject, body) VALUES (:user_id, :sender, :subject, :body)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ':user_id' => $userId,
                ':sender' => $from,
                ':subject' => $subject,
                ':body' => $body
            ));

            return array('success' => true, 'message' => 'Email processed successfully.');

        } catch (Exception $e) {
            http_response_code(500);
            return array('success' => false, 'error' => 'Database operation failed: ' . $e->getMessage());
        }
    }
    
    public function getEmails($user) {
        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT id, sender, subject, received_at FROM emails WHERE user_id = :user_id ORDER BY received_at DESC");
            $stmt->execute(array(':user_id' => $user['id']));
            $emails = $stmt->fetchAll();

            return array('success' => true, 'data' => $emails);
        } catch (Exception $e) {
            http_response_code(500);
            return array('success' => false, 'error' => 'Failed to fetch emails: ' . $e->getMessage());
        }
    }

    public function getEmailBody($id, $user_id) {
        if (!$id) {
            return array('success' => false, 'error' => 'Email ID is required.');
        }

        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = :id AND user_id = :user_id");
            $stmt->execute(array(':id' => $id, ':user_id' => $user_id));
            $email = $stmt->fetch();

            if (!$email) {
                http_response_code(404);
                return array('success' => false, 'error' => 'Email not found or you do not have permission to view it.');
            }

            return array('success' => true, 'data' => $email);
        } catch (Exception $e) {
            http_response_code(500);
            return array('success' => false, 'error' => 'Failed to fetch email body: ' . $e->getMessage());
        }
    }
}
