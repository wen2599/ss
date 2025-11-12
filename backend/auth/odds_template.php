<?php
// File: backend/auth/odds_template.php (修复版)
require_once __DIR__ . '/../db_operations.php';

// 1. 身份验证
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_db_connection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 获取用户赔率模板
        $stmt = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            // 如果没有模板，返回空模板结构
            $template = [
                'special_code_odds' => null,
                'flat_special_odds' => null,
                'serial_code_odds' => null,
                'even_xiao_odds' => null,
                'six_xiao_odds' => null,
                'size_single_double_odds' => null
            ];
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $template
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 更新用户赔率模板
        $input = json_decode(file_get_contents('php://input'), true);

        // 验证输入数据
        if (!$input) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
            exit;
        }

        // 安全地获取和转换数值
        $special_code_odds = isset($input['special_code_odds']) && $input['special_code_odds'] !== '' ? floatval($input['special_code_odds']) : null;
        $flat_special_odds = isset($input['flat_special_odds']) && $input['flat_special_odds'] !== '' ? floatval($input['flat_special_odds']) : null;
        $serial_code_odds = isset($input['serial_code_odds']) && $input['serial_code_odds'] !== '' ? floatval($input['serial_code_odds']) : null;
        $even_xiao_odds = isset($input['even_xiao_odds']) && $input['even_xiao_odds'] !== '' ? floatval($input['even_xiao_odds']) : null;
        $six_xiao_odds = isset($input['six_xiao_odds']) && $input['six_xiao_odds'] !== '' ? floatval($input['six_xiao_odds']) : null;
        $size_single_double_odds = isset($input['size_single_double_odds']) && $input['size_single_double_odds'] !== '' ? floatval($input['size_single_double_odds']) : null;

        // 检查是否已存在模板
        $stmt_check = $pdo->prepare("SELECT id FROM user_odds_templates WHERE user_id = ?");
        $stmt_check->execute([$user_id]);
        $existing_template = $stmt_check->fetch();

        if ($existing_template) {
            // 更新现有模板
            $stmt = $pdo->prepare("
                UPDATE user_odds_templates
                SET special_code_odds = ?, flat_special_odds = ?, serial_code_odds = ?,
                    even_xiao_odds = ?, six_xiao_odds = ?, size_single_double_odds = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");
            $result = $stmt->execute([
                $special_code_odds, $flat_special_odds, $serial_code_odds,
                $even_xiao_odds, $six_xiao_odds, $size_single_double_odds,
                $user_id
            ]);
        } else {
            // 插入新模板
            $stmt = $pdo->prepare("
                INSERT INTO user_odds_templates
                (user_id, special_code_odds, flat_special_odds, serial_code_odds,
                 even_xiao_odds, six_xiao_odds, size_single_double_odds)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $user_id, $special_code_odds, $flat_special_odds, $serial_code_odds,
                $even_xiao_odds, $six_xiao_odds, $size_single_double_odds
            ]);
        }

        if ($result) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => '赔率模板保存成功'
            ]);
        } else {
            throw new Exception('Failed to save template');
        }
    }

} catch (PDOException $e) {
    error_log("Database error in odds_template: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '数据库错误: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in odds_template: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '服务器错误: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log("Unexpected error in odds_template: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '未知错误: ' . $e->getMessage()]);
}
?>