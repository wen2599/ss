<?php
// setup_database.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';

echo "--> 正在尝试连接数据库...\n";

$conn = get_db_connection();
if (!$conn) {
    echo "!!! 错误: 数据库连接失败! 请仔细检查 .env 文件中的配置。\n";
    exit(1);
}
echo "--> ✅ 成功: 数据库已连接。\n\n";

// --- Step 1: Create tables from schema.sql ---
echo "--> 正在从 `backend/sql/schema.sql` 读取数据库结构...\n";
$schema_sql = file_get_contents(__DIR__ . '/sql/schema.sql');
if ($schema_sql === false) {
    echo "!!! 错误: 无法读取 schema.sql 文件。\n";
    exit(1);
}

// Flush out any existing results from previous queries if any
while ($conn->more_results() && $conn->next_result()) { 
    if ($result = $conn->store_result()) {
        $result->free();
    }
}

if ($conn->multi_query($schema_sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "--> ✅ 成功: 所有数据表已根据 schema.sql 创建或验证。\n\n";
} else {
    echo "!!! 错误: 执行 schema.sql 时出错! 原因: " . $conn->error . "\n\n";
    // Even if it fails, we should close the connection
    $conn->close();
    exit(1);
}

// --- Step 2: Insert or Update the default AI template ---
echo "--> 正在插入或更新默认的 AI 提示模板...\n";

$template_name = 'betting_slip_parser';
$model_name = 'gemini-pro';
// This prompt is crafted to be very specific for better results.
$prompt_text = <<<PROMPT
你是一个专业的投注单数据录入员。你的任务是从用户提供的原始文本中提取所有投注信息,并将其格式化为一个干净、标准的 JSON 对象。你必须严格遵守以下规则:

1.  **返回纯 JSON**: 你的最终输出必须是一个没有任何额外解释、注释或 Markdown 标记(`json`...`)的 JSON 对象。
2.  **JSON 结构**: JSON 对象必须包含以下三个顶级键:
    *   `issue` (string): 投注的期号。如果文本中没有明确提到,请将其设为 `null`。
    *   `bets` (array): 一个包含所有投注项的数组。每个投注项都是一个对象。
    *   `total_amount` (number): 所有投注项金额的总和。

3.  **投注项对象结构**: `bets` 数组中的每个对象都必须包含以下三个键:
    *   `type` (string): 投注类型。例如: "定位", "复式", "特码", "平码" 等。
    *   `content` (string): 投注的具体内容。例如: "123,456,789", "龙,虎", "35"。
    *   `amount` (number): 该投注项的金额。必须是一个数字。

4.  **数据清洗**: 
    *   自动忽略所有与投注无关的文本,如问候语、签名、闲聊等。
    *   如果文本中包含多个投注项,请确保将它们全部提取到 `bets` 数组中。
    *   仔细计算 `total_amount`。

**示例:**

如果用户输入:
"你好,这期 24001 期帮我买:
定位 123,456 各 10 元
特码 龙 100 元
总共是 120 元"

你的输出必须是:

```json
{
  "issue": "24001",
  "bets": [
    {
      "type": "定位",
      "content": "123,456",
      "amount": 10
    },
    {
      "type": "特码",
      "content": "龙",
      "amount": 100
    }
  ],
  "total_amount": 110
}
```
PROMPT;

// Use INSERT ... ON DUPLICATE KEY UPDATE to make the operation idempotent
$sql = "INSERT INTO ai_templates (name, prompt_text, model_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE prompt_text = VALUES(prompt_text), model_name = VALUES(model_name)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "!!! 错误: 无法准备 AI 模板的 SQL 语句! 原因: " . $conn->error . "\n";
    $conn->close();
    exit(1);
}

$stmt->bind_param("sss", $template_name, $prompt_text, $model_name);

if ($stmt->execute()) {
    echo "--> ✅ 成功: 默认的 AI 模板 '{$template_name}' 已成功插入或更新。\n\n";
} else {
    echo "!!! 错误: 执行 AI 模板插入时出错! 原因: " . $stmt->error . "\n\n";
}

$stmt->close();
$conn->close();

echo "--> 所有数据库操作已完成。\n";

?>
