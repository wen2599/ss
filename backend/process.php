<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

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
$text = $data['emailText'];

// a. 计算字符数 (使用 mb_strlen 以正确处理中文字符等多字节字符)
$char_count = mb_strlen($text, 'UTF-8');

// b. 计算单词数 (先用正则表达式替换掉标点符号，再用 str_word_count)
// \p{P} 匹配任何标点字符, \p{S} 匹配任何符号, \s 匹配空白字符
$cleaned_text_for_words = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $text);
$word_count = str_word_count($cleaned_text_for_words);

// c. 提取关键词 (一个简单的示例：提取所有长度大于4的英文单词和所有中文词组)
// ([a-zA-Z]{5,}) 匹配至少5个字母的英文单词
// ([\p{Han}]+) 匹配一个或多个连续的汉字
preg_match_all('/([a-zA-Z]{5,})|([\p{Han}]+)/u', $text, $matches);
// array_filter 移除空值, array_unique 去重
$keywords = array_unique(array_filter($matches[0]));

// 6. 准备成功的响应数据
$response = [
    'success' => true,
    'data' => [
        'charCount' => $char_count,
        'wordCount' => $word_count,
        // 使用 array_values 重建索引，确保 JSON 输出为数组 `[]` 而不是对象 `{}`
        'keywords' => array_values($keywords)
    ]
];

// 7. 发送 JSON 响应
http_response_code(200); // 200 OK
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // 使用参数让中文和格式更友好

?>
