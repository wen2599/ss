<?php
require_once 'db.php';
require_once 'routes.php';
require_once 'functions.php';

// 解析请求
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 分发路由
route($uri, $method);