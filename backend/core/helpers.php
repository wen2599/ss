<?php
// 文件名: helpers.php
// 路径: core/helpers.php
function is_admin($telegram_user_id) {
    if (!defined('TELEGRAM_ADMIN_ID')) return false;
    return (string)$telegram_user_id === (string)TELEGRAM_ADMIN_ID;
}

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}