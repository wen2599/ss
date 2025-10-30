<?php
/**
 * 文件名: get_latest.php
 * 路径: public_html/data/get_latest.php
 * 版本: Final Corrected - mysqli connection
 * 
 * 描述:
 * 此版本使用 mysqli 进行数据库连接，并放在已知可以执行 PHP 的 /data/ 目录下。
 * 这是最稳定的后端代码。
 */

//======================================================================
// 1. CORS 头
//======================================================================
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}
header("Content-Type: application/json; charset=UTF-8");

//======================================================================
// 2. 错误报告 (用于调试)
//======================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//======================================================================
// 3. 硬编码的数据库配置
//======================================================================
$db_host = 'mysql12.serv00.com';
$db_user = 'm10300_yh';
$db_pass = 'Wenxiu1234*';
$db_name = 'm10300_newdb'; // <-- 请最后确认这是您最新的数据库名
$db_port = 3306;

//======================================================================
// 4. 使用 mysqli 进行数据库连接
//======================================================================
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($mysqli->connect_error) {
    http_response_code(500);
    error_log("mysqli connection failed: " . $mysqli->connect_error);
    echo json_encode(['message' => 'Database connection failed.']);
    exit;
}
$mysqli->set_charset("utf8mb4");

//======================================================================
// 5. 业务逻辑 - 使用 mysqli 执行查询
//======================================================================
$sql = "SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1";
$result = $mysqli->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        http_response_code(200);
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'No lottery results found.']);
    }
} else {
    http_response_code(500);
    error_log("mysqli query failed: " . $mysqli->error);
    echo json_encode(['message' => 'Database query failed.']);
}

$mysqli->close();

?>