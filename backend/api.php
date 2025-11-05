<?php
require_once 'config.php';
require_once 'classes/AuthHandler.php';
require_once 'classes/EmailHandler.php';
require_once 'classes/LotteryHandler.php';

header('Content-Type: application/json');

class APIHandler {
    private $db;
    private $authHandler;
    private $emailHandler;
    private $lotteryHandler;

    public function __construct() {
        $this->db = new Database();
        $apiKey = Config::get('API_KEY');
        $workerSecret = Config::get('EMAIL_HANDLER_SECRET');

        $this->authHandler = new AuthHandler($this->db, $apiKey);
        $this->emailHandler = new EmailHandler($this->db, $workerSecret);
        $this->lotteryHandler = new LotteryHandler($this->db);
    }

    public function handleRequest() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'get_lottery_results';

        switch ($action) {
            case 'get_lottery_results':
                $this->authHandler->validateApiKey();
                $type = isset($_GET['type']) ? $_GET['type'] : null;
                $limit = isset($_GET['limit']) ? $_GET['limit'] : 20;
                $response = $this->lotteryHandler->getResults($type, $limit);
                break;

            case 'is_user_registered':
                $this->emailHandler->validateWorkerSecret();
                $response = $this->emailHandler->isUserRegistered();
                break;

            case 'process_email':
                $this->emailHandler->validateWorkerSecret();
                $response = $this->emailHandler->processEmail();
                break;

            case 'get_emails':
                $user = $this->authHandler->validateAuthToken();
                $response = $this->emailHandler->getEmails($user);
                break;

            case 'get_email_body':
                $user = $this->authHandler->validateAuthToken();
                $emailId = isset($_GET['id']) ? $_GET['id'] : null;
                $response = $this->emailHandler->getEmailBody($emailId, $user['id']);
                break;

            default:
                http_response_code(400);
                $response = array('success' => false, 'error' => 'Invalid action');
                break;
        }

        echo json_encode($response);
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