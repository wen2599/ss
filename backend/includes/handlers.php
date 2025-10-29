<?php
// backend/includes/handlers.php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * 全局异常处理器
 * @param Throwable $exception
 */
function global_exception_handler(Throwable $exception): void
{
    error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine() . "
" . $exception->getTraceAsString());
    
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'status' => 'error',
        'message' => '服务器发生意外错误，请稍后再试或联系管理员。'
    ];

    if (getenv('APP_DEBUG') === 'true') {
        $response['error_details'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
    }

    echo json_encode($response);
    exit;
}

/**
 * 全局错误处理器
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @return bool
 * @throws ErrorException
 */
function global_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // E_WARNING, E_NOTICE etc.
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

/**
 * 注册全局处理器
 */
function register_global_handlers(): void
{
    set_exception_handler('global_exception_handler');
    set_error_handler('global_error_handler');
}