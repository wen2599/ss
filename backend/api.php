<?php
require_once 'config.php';

header('Content-Type: application/json');

class APIHandler {
    private $db;
    private $apiKey;
    private $workerSecret;

    public function __construct() {
        $this->db = new Database();
        $this->apiKey = Config::get('API_KEY');
        $this->workerSecret = Config::get('EMAIL_HANDLER_SECRET');
    }

    private function validateWorkerSecret() {
        $clientSecret = isset($_GET['worker_secret']) ? $_GET['worker_secret'] : (isset($_POST['worker_secret']) ? $_POST['worker_secret'] : null);
        if (!$this->workerSecret || $clientSecret !== $this->workerSecret) {
            http_response_code(401);
            echo json_encode(array('success' => false, 'error' => 'Unauthorized worker'));
            exit;
        }
    }

    private function validateAuthToken() {
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

    private function validateApiKey() {
        $clientApiKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null;
        if ($clientApiKey !== $this->apiKey) {
            http_response_code(401);
            echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
            exit;
        }
    }

    public function handleRequest() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'get_lottery_results';

        switch ($action) {
            case 'get_lottery_results':
                $this->validateApiKey();
                $type = isset($_GET['type']) ? $_GET['type'] : null;
                $limit = isset($_GET['limit']) ? $_GET['limit'] : 20;
                $response = $this->getResults($type, $limit);
                break;

            case 'is_user_registered':
                $this->validateWorkerSecret();
                $response = $this->isUserRegistered();
                break;

            case 'process_email':
                $this->validateWorkerSecret();
                $response = $this->processEmail();
                break;

            case 'get_emails':
                $user = $this->validateAuthToken();
                $response = $this->getEmails($user);
                break;

            case 'get_email_body':
                $user = $this->validateAuthToken();
                $response = $this->getEmailBody($user);
                break;

            default:
                http_response_code(400);
                $response = array('success' => false, 'error' => 'Invalid action');
                break;
        }

        echo json_encode($response);
    }

    private function isUserRegistered() {
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

    private function processEmail() {
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
    
    private function getEmails($user) {
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

    private function getEmailBody($user) {
        $emailId = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$emailId) {
            return array('success' => false, 'error' => 'Email ID is required.');
        }

        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = :id AND user_id = :user_id");
            $stmt->execute(array(':id' => $emailId, ':user_id' => $user['id']));
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
    
    public function getResults($type = null, $limit = 20) {
        try {
            $pdo = $this->db->getConnection();
            $limit = max(1, intval($limit));

            $params = array();
            
            if ($type && in_array($type, array('双色球', '大乐透'))) {
                $sql = "SELECT * FROM lottery_results WHERE lottery_type = :type ORDER BY draw_date DESC, id DESC LIMIT :limit";
                $params[':type'] = $type;
            } else {
                $sql = "SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT :limit";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if (isset($params[':type'])) {
                 $stmt->bindValue(':type', $params[':type'], PDO::PARAM_STR);
            }

            $stmt->execute();
            $results = $stmt->fetchAll();
            
            return array(
                'success' => true,
                'data' => $results,
                'count' => count($results)
            );
            
        } catch (Exception $e) {
            http_response_code(500);
            return array(
                'success' => false,
                'error' => 'API Error: ' . $e->getMessage()
            );
        }
    }
}

try {
    $api = new APIHandler();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'API Initialization Error: ' . $e->getMessage()
    ]);
}
?>