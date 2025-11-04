<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class LotteryAPI {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getResults($type = null, $limit = 10) {
        try {
            $pdo = $this->db->getConnection();
            
            $sql = "SELECT * FROM lottery_results";
            $params = [];
            
            if ($type) {
                $sql .= " WHERE lottery_type = :type";
                $params[':type'] = $type;
            }
            
            $sql .= " ORDER BY draw_date DESC, created_at DESC LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            
            if ($type) {
                $stmt->bindValue(':type', $type);
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getLatestResult($type = null) {
        try {
            $pdo = $this->db->getConnection();
            
            $sql = "SELECT * FROM lottery_results";
            $params = [];
            
            if ($type) {
                $sql .= " WHERE lottery_type = :type";
                $params[':type'] = $type;
            }
            
            $sql .= " ORDER BY draw_date DESC, created_at DESC LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            if ($type) {
                $stmt->execute([':type' => $type]);
            } else {
                $stmt->execute();
            }
            
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'data' => $result ?: null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

$api = new LotteryAPI();

// 路由处理
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$queryString = $_SERVER['QUERY_STRING'] ?? '';
parse_str($queryString, $queryParams);

// 简单路由
if (strpos($path, '/api/results') !== false) {
    $type = $_GET['type'] ?? null;
    $limit = $_GET['limit'] ?? 10;
    
    $response = $api->getResults($type, $limit);
    echo json_encode($response);
    
} elseif (strpos($path, '/api/latest') !== false) {
    $type = $_GET['type'] ?? null;
    
    $response = $api->getLatestResult($type);
    echo json_encode($response);
    
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint not found'
    ]);
}
?>