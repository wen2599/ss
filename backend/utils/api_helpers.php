<?php

function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

function error_response($status_code, $message) {
    json_response($status_code, ['error' => $message]);
}
