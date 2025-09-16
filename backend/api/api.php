<?php
// === CORS 配置 ===
// 允许来自你的前端域名的请求
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
// 允许的 HTTP 方法
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// 允许的请求头
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// 允许发送 Cookies (如果你的应用需要用户认证或会话管理)
header("Access-Control-Allow-Credentials: true");
// 预检请求的缓存时间 (可选，但推荐)
header("Access-Control-Max-Age: 86400");

// 对于预检请求 (OPTIONS 方法)，直接退出，不执行后续业务逻辑
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit(0);
}

// === 错误报告设置 (生产环境应禁用或限制) ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === 设置响应内容类型为 JSON ===
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '未知错误'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['chat_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        $fileType = $file['type'];

        // 允许的文件类型检查 (可根据需要扩展)
        $allowedTypes = ['text/plain', 'application/json'];
        if (!in_array($fileType, $allowedTypes)) {
            $response['message'] = '文件类型不被允许。只接受TXT和JSON文件。';
            echo json_encode($response);
            exit();
        }

        // 文件大小限制 (例如：5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            $response['message'] = '文件太大，请上传小于5MB的文件。';
            echo json_encode($response);
            exit();
        }

        // 临时文件路径
        $uploadDir = __DIR__ . '/uploads/'; // 确保 'uploads' 目录存在且可写
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // 尝试创建目录
        }
        $fileDestination = $uploadDir . uniqid('chat_') . '_' . basename($fileName);

        if (move_uploaded_file($fileTmpName, $fileDestination)) {
            // 文件上传成功，现在开始解析
            $fileContent = file_get_contents($fileDestination);

            // === 聊天记录解析逻辑 ===
            $parsedData = [];
            $rawContentPreview = ''; // 用于前端显示原始文件内容的部分预览

            if ($fileContent) {
                // 取文件内容的前1000个字符作为预览
                $rawContentPreview = mb_substr($fileContent, 0, 1000, 'UTF-8');

                // 简单的WhatsApp TXT聊天记录解析示例
                // 假设格式为：[日期 时间] 发送者: 消息内容
                // 例如：[2023/01/01 10:00:00] Alice: Hello there!
                // 注意：这只是一个非常基础的示例，实际的聊天记录格式可能更复杂。
                $lines = explode("\n", $fileContent);
                $currentMessage = null; // 用于处理多行消息

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    // 尝试匹配日期、时间、发送者和消息开头
                    // 考虑 WhatsApp 导出的常见格式，例如：
                    // [1/1/23, 10:00:00 AM] Sender: Message content
                    // 或 [2023/1/1 10:00:00] Sender: Message content
                    // 这里使用一个更通用的模式，但可能需要根据实际情况调整
                    $pattern = '/^\[(\d{1,4}[-\/\.]\d{1,2}[-\/\.]\d{1,4}),?\s+(\d{1,2}:\d{1,2}(?::\d{1,2})?\s*(?:AM|PM)?)\]\s+([^:]+):\s+(.*)$/U';
                    if (preg_match($pattern, $line, $matches)) {
                        // 新消息开始
                        if ($currentMessage) {
                            $parsedData[] = $currentMessage;
                        }
                        $currentMessage = [
                            'Date' => trim($matches[1]),
                            'Time' => trim($matches[2]),
                            'Sender' => trim($matches[3]),
                            'Message' => trim($matches[4])
                        ];
                    } else if ($currentMessage) {
                        // 如果是多行消息，追加到上一条消息
                        $currentMessage['Message'] .= "\n" . $line;
                    }
                }
                // 添加最后一条消息
                if ($currentMessage) {
                    $parsedData[] = $currentMessage;
                }
            }

            // 删除临时上传的文件 (可选，取决于你是否需要保留)
            // unlink($fileDestination);

            $response = [
                'success' => true,
                'message' => '文件上传并解析成功。',
                'fileName' => $fileName,
                'fileSize' => $fileSize,
                'fileType' => $fileType,
                'rawContent' => $rawContentPreview, // 返回部分原始内容
                'parsedData' => $parsedData
            ];
        } else {
            $response['message'] = '文件移动失败，请检查服务器权限。';
        }
    } else {
        $response['message'] = '没有文件上传或发生上传错误。错误代码: ' . ($_FILES['chat_file']['error'] ?? '未知');
    }
} else {
    $response['message'] = '只接受 POST 请求。';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
