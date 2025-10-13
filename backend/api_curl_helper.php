<?php

/**
 * 通用的 API 调用辅助函数，处理 cURL 请求和基础错误。
 *
 * @param string $url API 的 URL 端点。
 * @param array $payload 要发送的请求体数据。
 * @param array $headers HTTP 请求头。
 * @param int $timeout 超时时间（秒）。
 * @return array 包含 http_code, response_body, curl_error 的数组。
 */
function _call_api_curl($url, $payload, $headers, $timeout = 90) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response_body' => $responseBody,
        'curl_error' => $curlError
    ];
}