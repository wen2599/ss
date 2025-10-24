### 项目描述文件

**项目概述:**
这个项目是一个包含前端（基于 Vue.js 和 Capacitor）、后端（基于 PHP）和 Telegram Bot 的应用程序。后端提供 RESTful API 用于用户管理、开奖结果查询和邮件处理。Telegram Bot 用于实时通知开奖结果和响应用户命令。

*服务器根目录在域名下 wenge.cloudns.ch
项目backend目录下等同于服务器根目录
```
~/domains/wenge.cloudns.ch/public_html/ 
├── .env                  <-- 环境变量配置文件 (实际部署时应在此处)
│   ├── api/              <-- API 端点和核心逻辑
│   │   ├── bootstrap.php       <-- 应用程序启动、环境变量加载、CORS 和错误处理
│   │   ├── bot_health_check.php<-- Telegram Bot 健康检查脚本
│   │   ├── index.php           <-- 后端主要的 API 路由入口
│   │   ├── webhook.php         <-- Telegram Bot Webhook 处理入口
│   │   ├── database/           <-- 数据库相关文件
│   │   │   ├── migration.php   <-- 运行数据库迁移的 CLI 脚本
│   │   │   └── migration.sql   <-- 数据库表结构定义
│   │   └── src/Controllers/    <-- 控制器目录，包含业务逻辑
│   │       ├── BaseController.php      <-- 基础控制器，提供通用功能 (JSON 响应, DB 连接)
│   │       ├── EmailController.php     <-- 处理邮件相关逻辑
│   │       ├── LotteryController.php   <-- 处理彩票开奖结果相关逻辑
│   │       ├── TelegramController.php  <-- 处理 Telegram Bot 消息和命令
│   │       └── UserController.php      <-- 处理用户注册、登录、注销和认证
│   └── setup.php           <-- (用途待确认, 可能是项目初始设置脚本)
├── frontend/             <-- 前端应用程序目录 (Vue.js / Capacitor)
│   ├── android/            <-- Android 平台特定文件
│   ├── src/                <-- 前端源代码
│   │   ├── assets/         <-- 静态资源
│   │   ├── router/         <-- Vue Router 配置
│   │   ├── store/          <-- Vuex 状态管理
│   │   └── views/          <-- Vue 视图组件
│   ├── capacitor.config.json   <-- Capacitor 配置文件
│   ├── index.html          <-- 前端应用程序入口 HTML
│   ├── package.json        <-- Node.js 项目依赖和脚本
│   ├── package-lock.json   <-- Node.js 依赖锁文件
│   └── vite.config.js      <-- Vite 打包工具配置文件
├── worker/               <-- 独立 Worker 脚本 (例如，用于后台任务)
│   └── worker.js           <-- Worker JavaScript 脚本
└── .git/                 <-- Git 版本控制信息
└── .github/              <-- GitHub Actions 配置等
└── .gitignore            <-- Git 忽略文件列表
└── .idx/                 <-- IDE 开发环境相关文件
└── .env.example          <-- `.env` 文件的示例 (不应包含敏感信息)
```

---

### 环境变量 (`.env`)

**位置:** `~/domains/wenge.cloudns.ch/public_html/.env` (项目的服务器根目录)

**重要配置项:**

*   **`DB_HOST`**: 数据库主机地址
*   **`DB_PORT`**: 数据库端口 (通常为 3306)
*   **`DB_NAME`**: 数据库名称
*   **`DB_USER`**: 数据库用户名
*   **`DB_PASSWORD`**: 数据库密码
*   **`EMAIL_HANDLER_SECRET`**: 邮件处理器的安全密钥
*   **`TELEGRAM_WEBHOOK_SECRET`**: Telegram Webhook 的安全令牌
*   **`ADMIN_SECRET`**: 管理员密钥
*   **`WORKER_SECRET`**: Worker 脚本的密钥
*   **`TELEGRAM_BOT_TOKEN`**: Telegram Bot 的 API Token
*   **`TELEGRAM_CHANNEL_ID`**: Telegram Bot 发布消息的频道 ID
*   **`TELEGRAM_ADMIN_ID`**: Telegram Bot 管理员的用户 ID (用于接收错误通知)
*   **`GEMINI_API_KEY`**: Gemini AI API 密钥
*   **`DEEPSEEK_API_KEY`**: DeepSeek AI API 密钥
*   **`BACKEND_PUBLIC_URL`**: 后端服务的公共可访问 URL (例如 `https://wenge.cloudns.ch`)
*   **`BACKEND_URL`**: (同 `BACKEND_PUBLIC_URL`，内部使用)
*   **`CLOUDFLARE_ACCOUNT_ID`**: Cloudflare 账户 ID (如果使用 Cloudflare)
*   **`CLOUDFLARE_API_TOKEN`**: Cloudflare API Token (如果使用 Cloudflare)
*   **`ALLOWED_ORIGINS`**: 允许进行跨域请求 (CORS) 的前端 URL (例如 `https://ss.wenxiuxiu.eu.org,http://localhost:5173`)
*   **`APP_DEBUG`**: 应用程序调试模式 (设置为 `true` 会显示更多错误信息)

---

### 后端 API 端点

**基础 URL:** `https://wenge.cloudns.ch/api/` (由 `backend/api/index.php` 路由)

#### **用户管理 (`UserController.php`)**

*   **注册用户**
    *   **方法:** `POST`
    *   **路径:** `/api/register`
    *   **请求体 (JSON):**
        ```json
        {
          "email": "user@example.com",
          "password": "yourpassword"
        }
        ```
    *   **响应:**
        *   `201 Created`: `{"status": "success", "message": "User registered successfully."}`
        *   `400 Bad Request`: `{"status": "error", "message": "Invalid or missing email."}` 或 `{"status": "error", "message": "Password must be at least 6 characters long."}`
        *   `409 Conflict`: `{"status": "error", "message": "Email already registered."}`
        *   `500 Internal Server Error`: `{"status": "error", "message": "Database error during registration."}`
*   **用户登录**
    *   **方法:** `POST`
    *   **路径:** `/api/login`
    *   **请求体 (JSON):**
        ```json
        {
          "email": "user@example.com",
          "password": "yourpassword"
        }
        ```
    *   **响应:**
        *   `200 OK`: `{"status": "success", "message": "Login successful.", "data": {"username": "user@example.com"}}`
        *   `400 Bad Request`: `{"status": "error", "message": "Email and password are required."}`
        *   `401 Unauthorized`: `{"status": "error", "message": "Invalid credentials."}`
        *   `500 Internal Server Error`: `{"status": "error", "message": "Database error during login."}`
*   **用户注销**
    *   **方法:** `POST`
    *   **路径:** `/api/logout`
    *   **响应:** `200 OK`: `{"status": "success", "message": "Logged out successfully."}`
*   **检查认证状态**
    *   **方法:** `GET`
    *   **路径:** `/api/check-auth`
    *   **响应:** `200 OK`: `{"status": "success", "data": {"isLoggedIn": true, "username": "user@example.com"}}` 或 `{"status": "success", "data": {"isLoggedIn": false}}`
*   **检查邮箱是否已注册**
    *   **方法:** `GET`
    *   **路径:** `/api/users/is-registered`
    *   **查询参数:**
        *   `email`: 要检查的邮箱地址。
        *   `worker_secret`: 用于验证请求的密钥 (对应 `.env` 中的 `EMAIL_HANDLER_SECRET` 或 `WORKER_SECRET`)。
    *   **示例:** `/api/users/is-registered?email=test@example.com&worker_secret=YOUR_WORKER_SECRET`
    *   **响应:**
        *   `200 OK`: `{"status": "success", "data": {"is_registered": true, "user_id": 1}}` 或 `{"status": "success", "data": {"is_registered": false}}`
        *   `400 Bad Request`: `{"status": "error", "message": "Invalid or missing email parameter."}`
        *   `403 Forbidden`: `{"status": "error", "message": "Forbidden: Invalid or missing secret."}`
        *   `500 Internal Server Error`: `{"status": "error", "message": "Database error."}`

#### **彩票结果 (`LotteryController.php`)**

*   **获取最新开奖结果**
    *   **方法:** `GET`
    *   **路径:** `/api/lottery-results`
    *   **响应:** `200 OK` 包含彩票结果的 JSON 数组。

#### **邮件处理 (`EmailController.php`)**

*   **处理传入邮件**
    *   **方法:** `POST`
    *   **路径:** `/api/emails`
    *   **请求体 (JSON):** 邮件数据，具体格式取决于邮件处理逻辑。
    *   **响应:** 邮件处理结果。

---

### Telegram Bot Webhook

**Webhook URL:** `https://wenge.cloudns.ch/api/webhook.php` (由 `backend/api/webhook.php` 直接处理)

此端点接收来自 Telegram Bot API 的所有更新。

**核心处理逻辑 (`TelegramController.php`):**

*   **`handleWebhook(array $update)`**: 主入口，解析 Telegram 更新并分发。
    *   **命令处理:** 如果消息以 `/` 开头，则作为命令处理。
        *   **`/start` 命令**:
            *   **描述:** 发送欢迎消息和可用命令列表。
            *   **实现:** `_handleStartCommand(string $chatId)` 方法。
        *   **`/lottery` 命令**:
            *   **描述:** 获取并发送最新的彩票开奖结果。
            *   **实现:** `_handleLotteryCommand(string $chatId)` 方法。
            *   内部调用 `LotteryController::fetchLatestResultsData()`。
    *   **开奖结果解析与保存:** 如果消息来自配置的 `TELEGRAM_CHANNEL_ID` 且不包含命令，则尝试解析并保存开奖结果。
        *   **实现:** `_parseAndSaveLotteryResult(string $text)` 方法。
        *   支持的格式: `新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)`, `香港六合彩第:(\d+)期开奖结果:\s*([\d\s]+)`, `老澳\d{2}\.\d{2}第:(\d+)\s*期开奖结果:\s*([\d\s]+)`.

---

### CLI 工具

*   **Telegram Bot 健康检查**
    *   **命令:** `php api/bot_health_check.php` (在服务器的 `public_html` 目录下执行)
    *   **用途:** 检查 `.env` 文件加载、`TELEGRAM_BOT_TOKEN` 和 `TELEGRAM_CHANNEL_ID` 是否设置、Telegram API 连接状态以及 Webhook 配置信息（包括最后错误消息）。
*   **数据库迁移**
    *   **命令:** `php backend/api/database/migration.php` (在服务器的 `public_html` 目录下执行)
    *   **用途:** 执行 `backend/api/database/migration.sql` 中定义的 SQL 语句，创建或更新数据库表结构。

---
