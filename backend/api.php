<?php
require_once 'config.php';

header('Content-Type: application/json');

class LotteryAPI {
    private $db;
    private $apiKey;

    public function __construct() {
        $this->db = new Database();
        $this->apiKey = Config::get('API_KEY');
    }

    private function validateApiKey() {
        $clientApiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if ($clientApiKey !== $this->apiKey) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }

    public function handleRequest() {
        // Apply API Key validation to all API requests
        $this->validateApiKey();

        $type = $_GET['type'] ?? null;
        $limit = $_GET['limit'] ?? 20;

        $response = $this->getResults($type, $limit);
        echo json_encode($response);
    }
    
    public function getResults($type = null, $limit = 20) {
        try {
            $pdo = $this->db->getConnection();
            $limit = max(1, intval($limit));

            $params = [];
            
            if ($type && in_array($type, ['双色球', '大乐透'])) {
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
            
            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'API Error: ' . $e->getMessage()
            ];
        }
    }
}

$api = new LotteryAPI();
$api->handleRequest();
?>
