<?php
// 在文件最开头设置CORS头
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 设置内容类型
header('Content-Type: application/json; charset=utf-8');

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

class LotteryAPI {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function getResults($type = null, $limit = 10) {
        try {
            $pdo = $this->db->getConnection();
            
            $sql = "SELECT * FROM lottery_results WHERE 1=1";
            $params = [];
            
            if ($type && $type !== 'all') {
                $sql .= " AND lottery_type = :type";
                $params[':type'] = $type;
            }
            
            $sql .= " ORDER BY draw_date DESC, created_at DESC LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            
            if ($type && $type !== 'all') {
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
            error_log("API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to fetch results'
            ];
        }
    }
    
    public function getLatestResult($type = null) {
        try {
            $pdo = $this->db->getConnection();
            
            $sql = "SELECT * FROM lottery_results WHERE 1=1";
            $params = [];
            
            if ($type && $type !== 'all') {
                $sql .= " AND lottery_type = :type";
                $params[':type'] = $type;
            }
            
            $sql .= " ORDER BY draw_date DESC, created_at DESC LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            if ($type && $type !== 'all') {
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
            error_log("API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to fetch latest result'
            ];
        }
    }
    
    private function sendError($message) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}

// 创建API实例
$api = new LotteryAPI();

// 获取请求路径
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// 简单路由
try {
    if (strpos($path, '/api/results') !== false) {
        $type = $_GET['type'] ?? null;
        $limit = $_GET['limit'] ?? 20;
        
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
            'error' => 'Endpoint not found',
            'available_endpoints' => [
                '/api/results?type=[type]&limit=[limit]',
                '/api/latest?type=[type]'
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>