<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>后端终极诊断脚本 (v2)</h1>";
echo "<p>本脚本将对服务器的文件系统和权限进行最终测试。</p><hr>";

// Test 1: PHP User
echo "<h2>测试 1: PHP 进程用户</h2>";
$process_user = '无法确定';
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $process_user_info = posix_getpwuid(posix_geteuid());
    $process_user = $process_user_info['name'];
} elseif (function_exists('exec')) {
    $process_user = exec('whoami');
}
echo "<p>运行此PHP脚本的用户是: <b>" . htmlspecialchars($process_user) . "</b></p>";
echo "<p><i>(这有助于我们理解文件权限)</i></p>";
echo "<hr>";


// Test 2: Sessions Directory
echo "<h2>测试 2: Session 目录权限</h2>";
$session_path = __DIR__ . '/sessions';

if (!is_dir($session_path)) {
    echo "<p><font color='red'><b>错误:</b> 目录 <code>" . htmlspecialchars($session_path) . "</code> 不存在！</font></p>";
    echo "<p>请先运行部署流程创建该目录。</p>";
} else {
    echo "<p><font color='green'>成功:</font> 目录 <code>" . htmlspecialchars($session_path) . "</code> 已存在。</p>";

    // Test for writability
    if (!is_writable($session_path)) {
        echo "<p><font color='red'><b>致命错误:</b> PHP报告说目录 <code>" . htmlspecialchars($session_path) . "</code> <b>不可写</b>！</font></p>";
        echo "<p>这是导致502错误的根本原因。您可能需要通过FTP或SSH客户端，将此目录的权限设置为 755 或 775。</p>";
    } else {
        echo "<p><font color='green'>成功:</font> PHP报告说目录 <code>" . htmlspecialchars($session_path) . "</code> <b>可写</b>。</p>";

        // Final confirmation: try to actually write a file
        $test_file = $session_path . '/test_write_' . time() . '.tmp';
        if (@file_put_contents($test_file, 'hello')) {
            echo "<p><font color='green'><b>最终确认成功:</b> 已成功在此目录中创建了一个测试文件。</font></p>";
            @unlink($test_file);
        } else {
            echo "<p><font color='red'><b>致命错误:</b> 尽管PHP认为目录可写，但实际创建文件失败了！</font></p>";
             echo "<p>这绝对是导致502错误的根本原因。请检查服务器的磁盘空间或联系服务商解决文件系统问题。</p>";
        }
    }
}
echo "<hr>";

echo "<h2>诊断完成</h2>";
?>