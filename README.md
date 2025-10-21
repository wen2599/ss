# 账单与彩票追踪器

该项目提供了一个全栈解决方案，利用人工智能自动处理电子邮件中的账单，并追踪彩票结果。它由一个部署在 Cloudflare Pages 上的 React 前端、一个部署在 Serv00 上的纯 PHP 后端以及一个用于电子邮件处理的 Cloudflare Worker 组成。

## 项目功能

*   **用户管理**：通过前端界面或 Telegram Bot 进行用户注册和认证。
*   **账单追踪**：查看和管理从电子邮件中解析出的账单。 （例如：`我的账单` 页面显示 `未找到任何账单。请开始将您的账单转发到您注册的邮箱！` 或列出账单详情）。
*   **彩票结果**：追踪和查看最新的彩票中奖号码。
*   **AI 驱动的电子邮件解析**：使用 Gemini 或 Cloudflare AI 从电子邮件中提取账单详情和彩票号码。（例如：识别电子邮件中的 `总金额` 和 `到期日`，或 `彩票号码`）。
*   **Telegram Bot 互动**：通过 Telegram Bot 进行用户交互、注册、登录、查询账单和中奖通知。（例如：当用户彩票中奖时，Bot 会发送 `恭喜！您的彩票中奖了！` 等通知）。
*   **自动化任务**：通过 Cron Job 自动检查彩票结果并通知中奖者。

## 项目架构

*   **前端 (Frontend)**：React 应用程序，提供用户界面，部署在 Cloudflare Pages。
*   **后端 (Backend)**：纯 PHP 应用程序，提供 API 端点、数据库交互和 Telegram Bot 逻辑，部署在 Serv00，作为服务器的根目录提供服务。
*   **Cloudflare Worker**：拦截传入的电子邮件，解析它们，并将原始电子邮件内容转发到后端进行 AI 处理。
*   **数据库 (Database)**：MySQL（或兼容）数据库，用于存储用户信息、账单、会话和彩票结果。

## 设置与部署

### 1. 后端 (Serv00)

#### A. 前提条件

*   一个支持纯 PHP 和 MySQL 的托管环境（例如 Serv00）。
*   已创建 Telegram Bot 并获取 Bot Token。
*   Google Gemini API 密钥或 Cloudflare AI 账户详情。

#### B. 配置

1.  **数据库**：使用 `backend/database_schema.sql` 中的 SQL 语句创建数据库表。
2.  **环境变量**：在您的后端环境根目录创建一个 `.env` 文件。您可以使用提供的 `.env.example` 作为模板。这是一个关键步骤，用于安全地存储您的凭据。

    ```dotenv
    # --- 数据库凭据 ---
    DB_HOST="your_database_host"
    DB_NAME="your_database_name"
    DB_USER="your_database_user"
    DB_PASS="your_database_password"

    # --- AI 服务配置 ---
    # 选择 'GEMINI' 或 'CLOUDFLARE' 作为激活的 AI 服务
    ACTIVE_AI_SERVICE="GEMINI"
    GEMINI_API_KEY="your_gemini_api_key"
    CLOUDFLARE_ACCOUNT_ID="your_cloudflare_account_id"
    CLOUDFLARE_API_TOKEN="your_cloudflare_api_token"

    # --- Telegram Bot 配置 ---
    TELEGRAM_BOT_TOKEN="your_telegram_bot_token"
    TELEGRAM_WEBHOOK_SECRET="a_very_strong_and_random_secret_string_for_webhook"
    TELEGRAM_ADMIN_ID="your_telegram_admin_user_id" # 用于接收管理通知
    LOTTERY_CHANNEL_ID="your_public_lottery_channel_id" # (可选)

    # --- 安全密钥 ---
    ADMIN_SECRET="another_very_strong_random_secret_for_admin_actions"
    EMAIL_HANDLER_SECRET="a_third_strong_secret_for_email_worker"

    # --- 前端公共 URL ---
    FRONTEND_PUBLIC_URL="https://your_frontend_public_url.com" # 您的前端公共 URL
    
    # --- 后端公共 URL ---
    BACKEND_PUBLIC_URL="https://your_backend_public_url.com" # 您的后端公共 URL
    ```

#### C. 部署后端文件

将 `backend` 目录中的所有文件和文件夹上传到 PHP 应用程序的根目录。

#### D. 设置 Telegram Webhook

一旦您的后端部署完成并通过 `https://your_backend_public_url.com` 可访问，请使用 cURL 或任何 API 工具向以下 URL 发送一个 **POST** 请求来设置 Telegram Webhook。**不要**在浏览器中直接访问此 URL。

**请求 URL:**
`https://your_backend_public_url.com/admin/set_telegram_webhook`

**请求方法:**
`POST`

**请求体 (JSON):**
```json
{
    "secret": "your_admin_secret_here"
}
```
*请将 `your_admin_secret_here` 替换为您在 `.env` 文件中设置的 `ADMIN_SECRET`。*

成功后，您应该看到 `{"ok":true,"result":true,"description":"Webhook was set"}`。

#### E. 配置 Cron Jobs

在您的托管服务上设置 Cron Jobs 以自动化任务：

1.  **彩票检查**：定期运行 `backend/check_lottery_data.php`。例如，每天运行一次：
    ```bash
    0 18 * * * php /path/to/your/backend/check_lottery_data.php
    ```
2.  **AI 邮件处理**：定期运行 `backend/process_email_ai.php` 来处理未处理的邮件。
    ```bash
    */5 * * * * php /path/to/your/backend/process_email_ai.php
    ```
*(请根据您的实际路径调整 `/path/to/your/backend/`，并根据您希望的计划调整 cron 时间表。)*

### 2. 前端 (Cloudflare Pages)

#### A. 前提条件

*   本地安装 Node.js 和 npm。
*   已设置 Cloudflare Pages 的 Cloudflare 账户。

#### B. 配置

请确保 `frontend/vite.config.js` 中的 `server.proxy` 设置已被移除或注释掉，因为前端将直接与您的 `BACKEND_PUBLIC_URL` 通信。

#### C. 构建与部署

1.  在 `frontend` 目录中运行 `npm install` 来安装依赖项。
2.  运行 `npm run build` 来构建生产版本。
3.  将 `frontend/dist` 目录部署到 Cloudflare Pages。

### 3. Cloudflare Worker (用于邮件处理)

#### A. 前提条件

*   一个配置了 Cloudflare 电子邮件路由的域名。

#### B. 部署 Worker

1.  将 `worker/worker.js` 脚本部署到 Cloudflare Workers。
2.  **环境变量**：在您的 Cloudflare Worker 设置中，添加以下环境变量：
    *   `PUBLIC_API_ENDPOINT`: 您的后端 `email_webhook` 端点的完整 URL (例如 `https://your_backend_public_url.com/email_webhook`)。
    *   `EMAIL_HANDLER_SECRET`: 与您后端 `.env` 文件中 `EMAIL_HANDLER_SECRET` 相同的密钥字符串。
3.  **电子邮件路由**：配置 Cloudflare 电子邮件路由，将指定地址（例如 `bills@yourdomain.com`）的电子邮件转发到此 Worker。Worker 将处理并将其转发到您的后端。

## 使用方法

### 前端应用

访问您部署在 Cloudflare Pages 上的前端应用。

*   **注册**：创建新账户。
*   **登录**：登录以查看您的账单和彩票结果。
*   **我的账单**：查看从电子邮件中解析出的账单列表。
*   **彩票结果**：查看最新的彩票中奖号码。

### Telegram Bot

与您的 Telegram Bot 互动：

*   `/start`：获取欢迎消息和命令列表。
*   `/register`：通过引导对话创建新用户账户。
*   `/login`：将您的 Telegram 聊天链接到现有 Web 账户。
*   `/bills`：查看您的最新账单。

### 电子邮件处理

将您的账单邮件转发到您配置了 Cloudflare 电子邮件路由的电子邮件地址（例如 `bills@yourdomain.com`）。Cloudflare Worker 将拦截并将其发送到您的后端进行 AI 处理。
