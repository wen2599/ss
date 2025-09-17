<?php
header('Content-Type: application/json');

// 检查请求方法是否为 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit();
}

// 检查是否有上传的文件
if (empty($_FILES['chat_file'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded with key "chat_file".']);
    exit();
}

$file = $_FILES['chat_file'];

// 检查文件上传是否有错误
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'File upload error: ' . $file['error']]);
    exit();
}

// 获取文件信息
$fileName = basename($file['name']); // 获取原始文件名
$tmpFilePath = $file['tmp_name'];   // 临时存储路径
$fileSize = $file['size'];         // 文件大小
$fileType = $file['type'];         // 文件类型

// 定义存储文件的目录 (使用绝对路径)
$uploadDir = '/usr/home/wenge95222/domains/wenge.cloudns.ch/public_html/uploads/';

// 确保上传目录存在
if (!is_dir($uploadDir)) {
    // 尝试递归创建目录，并设置宽松的权限
    if (!mkdir($uploadDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory. Check permissions.']);
        exit();
    }
}

$destinationPath = $uploadDir . $fileName;

// 移动上传的文件到目标目录
if (move_uploaded_file($tmpFilePath, $destinationPath)) {
    // 文件成功上传
    http_response_code(200); // OK
    echo json_encode([
        'status' => 'success',
        'message' => 'File uploaded successfully!',
        'filename' => $fileName,
        'size' => $fileSize,
        'path' => $destinationPath
    ]);
} else {
    // 文件移动失败
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file. Check directory permissions.']);
}

?>
