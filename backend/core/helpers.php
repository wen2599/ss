<?php
// 文件名: helpers.php
// 路径: backend/core/helpers.php

// 检查当前请求的用户是否是管理员
function is_admin($telegram_user_id) {
    if (!defined('TELEGRAM_ADMIN_ID')) {
        return false;
    }
    return (string)$telegram_user_id === (string)TELEGRAM_ADMIN_ID;
}

// 统一的JSON响应函数
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}