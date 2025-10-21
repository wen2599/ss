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

*   一个拥有 PHP 和 MySQL 数据库访问权限的 Serv00 账户。
*   PHP 8.1+，并启用 `pdo_mysql`、`curl`、`json` 扩展。
*   配置正确的 `.env` 文件（见下文）。

#### B. 数据库设置

1.  **创建数据库**：登录您的 Serv00 面板并创建一个新的 MySQL 数据库。
2.  **初始化数据表**：将 `backend/database_schema.sql` 上传到您的 Serv00 服务器。您可以通过 phpMyAdmin 导入，或通过 SSH 运行 `backend/initialize_database.php` 脚本：
    ```bash
    php /path/to/your/backend/initialize_database.php
    ```
    *请确保 `backend` 目录是您的 PHP 部署的根目录，或者您的 Web 服务器已相应配置。*

#### C. 环境变量 (`backend/.env`)

在您的 Serv00 `backend` 目录中创建一个 `.env` 文件，内容如下。**请用您的实际凭据替换占位符值。**

```env
# --- Database Configuration ---
DB_HOST="your_db_host" # 您的数据库主机
DB_PORT="3306"
DB_DATABASE="your_db_name" # 您的数据库名称
DB_USER="your_db_user"     # 您的数据库用户
DB_PASSWORD="your_db_password" # 您的数据库密码

# --- Security Tokens ---
# 一个强随机字符串。必须与 Cloudflare Worker (WORKER_SECRET) 和后端 (EMAIL_HANDLER_SECRET) 中的值匹配。
EMAIL_HANDLER_SECRET="your_email_handler_secret"
# 用于 Telegram Webhook secret_token 的强随机字符串。
TELEGRAM_WEBHOOK_SECRET="your_telegram_webhook_secret"
# 用于保护管理端点的强随机字符串。
ADMIN_SECRET="your_admin_secret_here"

# --- Telegram Bot Configuration ---
TELEGRAM_BOT_TOKEN="your_telegram_bot_token" # 您的 Telegram Bot API Token
TELEGRAM_ADMIN_ID="your_telegram_admin_id" # 您的个人 Telegram 用户 ID，用于接收管理通知
LOTTERY_CHANNEL_ID="your_lottery_channel_id" # 可选：用于宣布彩票中奖者的 Telegram 频道 ID

# --- AI API Keys ---
GEMINI_API_KEY="your_gemini_api_key" # 您的 Google Gemini API Key
DEEPSEEK_API_KEY="sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" # 如果使用 DeepSeek AI

# --- Cloudflare AI Configuration (如果使用 Cloudflare AI) ---
CLOUDFLARE_ACCOUNT_ID="your_cloudflare_account_id" # 您的 Cloudflare 账户 ID
CLOUDFLARE_API_TOKEN="your_cloudflare_api_token" # 您的 Cloudflare API Token (用于 AI Gateway)

# --- 后端公共 URL ---
BACKEND_PUBLIC_URL="https://your_backend_public_url.com" # 您的后端公共 URL
```

#### D. 部署后端文件

将 `backend` 目录中的所有文件和文件夹上传到 Serv00 PHP 应用程序的根目录。

#### E. 设置 Telegram Webhook

一旦您的后端部署完成并通过 `https://your_backend_public_url.com` 可访问，请通过在浏览器中访问以下 URL 来设置 Telegram Webhook（请替换 `your_admin_secret_here` 为您 `.env` 中设置的 `ADMIN_SECRET`）：

`https://your_backend_public_url.com/admin/set_telegram_webhook?secret=your_admin_secret_here`

成功后，您应该看到 `{"ok":true,"result":true,"description":"Webhook was set"}`。

#### F. 配置彩票检查 Cron Job

在 Serv00 上设置一个 Cron Job，定期运行 `backend/check_lottery_data.php`。例如，每天运行一次：

```bash
0 18 * * * php /path/to/your/backend/check_lottery_data.php
```
*(请根据您 Serv00 上的实际路径调整 `/path/to/your/backend/`，并根据您希望的计划调整 `0 18 * * *`。)*

### 2. 前端 (Cloudflare Pages)

#### A. 前提条件

*   本地安装 Node.js 和 npm。
*   已设置 Cloudflare Pages 的 Cloudflare 账户。

#### B. 配置

请确保 `frontend/src/api.js` 中的 `API_BASE_URL` 设置为空字符串，因为它将直接访问后端根目录：

```javascript
const API_BASE_URL = ''; // The backend directory is served as the root
// ...
```

#### C. 构建和部署

1.  在本地终端中导航到 `frontend` 目录：
    ```bash
    cd frontend
    ```
2.  安装依赖：
    ```bash
    npm install
    ```
3.  构建生产环境项目：
    ```bash
    npm run build
    ```
4.  将 `dist` 文件夹中的内容部署到您的 Cloudflare Pages。配置 Cloudflare Pages 从 `frontend` 目录构建并发布 `dist` 目录。
    *重要提示：由于后端作为服务器根目录，您可能需要将 `frontend/dist` 的内容复制到您服务器的 `backend` 根目录下，以便前端文件能够被提供。*

### 3. Cloudflare Worker (电子邮件处理)

#### A. 前提条件

*   一个 Cloudflare 账户。
*   一个已配置 Cloudflare 电子邮件路由的电子邮件地址。

#### B. Worker 脚本 (`worker/worker.js`)

请根据您的环境配置 `worker/worker.js` 中的 `backendUrl` 和 `workerSecret`。确保 `workerSecret` 与您后端 `.env` 中的 `EMAIL_HANDLER_SECRET` 匹配。

#### C. 部署 Worker

1.  将 `worker/worker.js` 脚本部署到 Cloudflare Workers。
2.  **环境变量**：在您的 Cloudflare Worker 设置中，添加以下环境变量：
    *   `BACKEND_URL`: 您的后端公共 URL (例如 `https://your_backend_public_url.com`)。
    *   `WORKER_SECRET`: 与您后端 `.env` 文件中 `EMAIL_HANDLER_SECRET` 相同的密钥字符串。
3.  **电子邮件路由**：配置 Cloudflare 电子邮件路由，将指定地址（例如 `bills@yourdomain.com`）的电子邮件转发到此 Worker。Worker 将处理并将其转发到您的后端。

## 使用方法

### 前端应用

访问您的前端应用程序 `https://ss.wenxiuxiu.eu.org`。

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

将您的账单邮件转发到您配置了 Cloudflare 电子邮件路由的电子邮件地址（例如 `bills@yourdomain.com`）。Cloudflare Worker 将拦截、解析并将其发送到您的后端进行 AI 处理。

## 开发注意事项

*   **安全性**：始终使用强、随机的密钥和 API 密钥。不要暴露敏感信息。
*   **错误日志**：确保您的 Serv00 PHP 环境中设置了适当的错误日志，以便进行调试。
*   **AI 模型**：您可以通过修改 `backend/process_email_ai.php` 中的 `ACTIVE_AI_SERVICE` 定义来在 Gemini 和 Cloudflare AI 之间切换。
