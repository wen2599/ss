<?php
/**
 * 文件名: helpers.php
 * 路径: backend/core/helpers.php
 * 版本: Final
 */
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}