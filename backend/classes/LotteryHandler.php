<?php

class LotteryHandler {
    private $db;

    public function __construct($db) {
        $this->db = $db;
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
