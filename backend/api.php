<?php
require_once 'config.php';

header('Content-Type: application/json');

class LotteryAPI {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getResults($type = null, $limit = 20) {
        try {
            $pdo = $this->db->getConnection();
            $limit = max(1, intval($limit)); // Ensure limit is a positive integer

            $params = [\':limit\' => $limit];
            
            if ($type && in_array($type, ['双色球', '大乐透'])) {
                $sql = "SELECT * FROM lottery_results WHERE lottery_type = :type ORDER BY draw_date DESC, id DESC LIMIT :limit";
                $params[':type'] = $type;
            } else {
                $sql = "SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT :limit";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if (isset($params[':type'])) {
                 $stmt->bindValue(':type', $params[':type']);
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

// Routing
$api = new LotteryAPI();
$type = $_GET['type'] ?? null;
$limit = $_GET['limit'] ?? 20;

$response = $api->getResults($type, $limit);

echo json_encode($response);
?>
