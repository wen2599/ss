<?php
// backend/utils/functions.php

/**
 * Sends a JSON response.
 *
 * @param mixed $data The data to encode as JSON.
 * @param int $statusCode The HTTP status code to send.
 */
function send_json_response($data, $statusCode = 200) {
    // 移除 CORS 头，因为代理解决了跨域问题
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// handle_options_request 函数可以删除了，因为它不再需要
// (为了代码完整性，这里保留为空，或者直接删除该函数)
function handle_options_request() {
    // This function is no longer needed.
}
