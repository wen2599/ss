<?php

class Response {
    public static $is_testing = false;

    public static function send_json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        if (!self::$is_testing) {
            exit();
        }
    }

    public static function send_json_error($code, $message, $details = null) {
        $response = ['success' => false, 'message' => $message];
        if ($details) {
            $response['details'] = $details;
        }
        self::send_json($response, $code);
    }
}
