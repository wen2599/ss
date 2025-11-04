# 全栈彩票展示与邮件处理平台

本项目是一个功能丰富的全栈应用，包含前端、后端和 Cloudflare Worker 三个核心部分。

## 功能概览

1.  **彩票结果展示**: 从 Telegram 频道自动获取彩票开奖结果，并通过 API 接口提供给前端展示。
2.  **邮件处理系统**: 通过 Cloudflare Email Routing 接收邮件，由 Worker 进行预处理和用户验证，最后交由 PHP 后端进行解析和存储。

---

## 架构

本项目的架构分为三个独立但紧密协作的部分：

### 1. 前端 (`frontend/`)

*   **技术栈**: React + Vite
*   **功能**: 一个单页应用 (SPA)，用于向用户展示彩票开奖结果。
*   **部署**: 部署在 Cloudflare Pages 上。

### 2. 后端 (`backend/`)

*   **技术栈**: "纯" PHP (无外部依赖)
*   **功能**:
    *   提供 `/api/results` 接口，供前端查询彩票结果。
    *   提供 Webhook (`/bot.php`)，用于接收和处理来自 Telegram 频道的消息。
    *   提供邮件处理接口，用于验证用户和存储邮件。
*   **部署**: 直接将 `backend/` 目录下的所有文件部署到 Web 服务器的根目录（例如 `public_html`）。**注意**: URL 路径中不应包含 `/backend`。例如，`backend/api.php` 在服务器上应通过 `https://your-backend.com/api.php` 访问。

### 3. Cloudflare Worker (`worker/`)

*   **功能**: Worker 是整个系统的核心网关和粘合剂。
    *   **API 网关**: 拦截所有对前端域名 (`https://your-frontend.com`) 的请求。
        *   如果请求路径以 `/api/` 开头，它会**代理**该请求到 PHP 后端，并在此过程中**安全地附加 `X-API-KEY`**。这避免了将敏感密钥暴露在前端代码中。
        *   对于所有其他请求，它会将其交由 Cloudflare Pages 服务处理，从而渲染前端应用。
    *   **邮件处理器**: 作为一个 Email Handler，接收邮件，调用后端进行发件人验证，然后将邮件内容转发给后端处理。
*   **部署**: 通过 Wrangler CLI 部署到 Cloudflare。

---

## 环境配置

正确的环境变量配置是项目成功运行的关键。

### 后端 (`backend/.env`)

在 `backend` 目录下创建一个 `.env` 文件，并包含以下变量：

```dotenv
# 数据库配置
DB_HOST=your_database_host
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

# Telegram Bot 配置
BOT_TOKEN=your_telegram_bot_token
CHANNEL_ID=your_telegram_channel_id
# 用于保护 Webhook 安全，应为一个长而随机的字符串
TELEGRAM_WEBHOOK_SECRET=your_webhook_secret_token

# API 密钥 (用于保护后端 API)
# 这个密钥必须与 Cloudflare Worker 中配置的 API_KEY 保持一致
API_KEY=your_secret_api_key
```

### Cloudflare Worker (在 Cloudflare 控制台配置)

在 Worker 的 `Settings > Variables` 中配置以下环境变量和绑定：

*   **Environment Variables**:
    *   `PUBLIC_API_ENDPOINT`: 你的 PHP 后端的完整 URL (例如 `https://wenge.cloudns.ch/email_handler.php`)。
    *   `EMAIL_HANDLER_SECRET`: 用于保护邮件处理接口的密钥。
    *   `API_KEY`: 用于访问后端 API 的密钥。**必须与 `backend/.env` 中的 `API_KEY` 相同**。
*   **Pages Project Binding**:
    *   创建一个名为 `ASSETS` 的绑定，并将其指向你的 Cloudflare Pages 前端项目。这是让 Worker 能够服务前端应用的关键。

---

## 本地开发

要运行前端开发服务器：

1.  进入 `frontend` 目录: `cd frontend`
2.  安装依赖: `npm install`
3.  启动开发服务器: `npm run dev`

开发服务器将自动代理 `/api` 请求到 `vite.config.js` 中配置的生产后端地址。

---

## 部署

*   **前端**: 在 `frontend` 目录下运行 `npm run deploy`，该命令会先构建项目，然后使用 Wrangler 将其部署到 Cloudflare Pages。
*   **后端**: 将 `backend/` 目录下的所有文件上传到你的 PHP Web 服务器的根目录。
*   **Worker**: 使用 Wrangler CLI 从 `worker/` 目录部署。确保在部署前已在 Cloudflare 控制台配置好所有环境变量和绑定。
