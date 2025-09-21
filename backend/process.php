<?php
/**
 * 邮件文本处理 API
 * 
 * 此脚本接收一个包含 "emailText" 字段的 JSON POST 请求，
 * 对文本进行分析（计算字数、字符数、提取关键词），
 * 并以 JSON 格式返回结果。
 *
 * 部署在 serv00 上，由 Cloudflare Worker 代理访问。
 */

// 1. 设置响应头：声明返回的内容是 JSON 格式
// 这是唯一需要的 header，用于告诉客户端如何解析响应体。
header("Content-Type: application/json; charset=utf-8");

// 2. 检查请求方法：确保 API 只接受 POST 请求
// 这是一个良好的 API 设计实践，防止通过 GET 等方法意外访问。
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 405 Method Not Allowed
    // 返回一个标准的错误信息
    echo json_encode([
        'success' => false,
        'error' => '请求方法不被允许，请使用 POST 方法。'
    ]);
    exit(); // 终止脚本执行
}

// 3. 获取并解析请求体
// 'php://input' 是一个只读流，可以读取请求的原始数据。
// 对于 JSON 请求，这是获取数据的标准方式。
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true); // `true` 将 JSON 对象转换为 PHP 关联数组

// 4. 验证输入数据
// 检查 JSON 是否有效，以及是否包含我们需要的 'emailText' 字段。
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['emailText'])) {
    http_response_code(400); // 400 Bad Request
    echo json_encode([
        'success' => false,
        'error' => '无效的请求数据。请确保发送的 JSON 格式正确且包含 "emailText" 字段。'
    ]);
    exit();
}

// 5. 核心处理逻辑
require_once __DIR__ . '/lib/TextAnalyzer.php';

$text = $data['emailText'];

$analyzer = new TextAnalyzer();
$analysis_result = $analyzer->analyze($text);

// 6. 准备成功的响应数据
$response = [
    'success' => true,
    'data' => $analysis_result
];

// 7. 发送 JSON 响应
http_response_code(200); // 200 OK
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // 使用参数让中文和格式更友好

?>
