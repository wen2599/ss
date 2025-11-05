# Telegram Bot 六合彩邮件下单系统

本项目是一个全栈应用，允许用户通过邮件发送下注单，由管理员在 Telegram 开奖，系统自动通过 AI 识别邮件并生成结算单。

## 技术栈

- **前端**: React + Vite，部署于 Cloudflare Pages。
- **后端**: 纯 PHP + MySQL，部署于 Serv00 共享主机。
- **Telegram Bot**: 纯 PHP Webhook 模式。
- **邮件接收**: Cloudflare Email Routing + Cloudflare Workers。
- **AI 服务**: Cloudflare AI + Google Gemini (备用)。

## 部署指南

### 后端部署 (Serv00)

1.  将 `backend/` 目录下的所有文件上传到你的 Serv00 主机空间（例如 `/home/youruser/public_html/`）。
2.  在 Serv00 控制面板创建一个 MySQL 数据库，并记下数据库名、用户名和密码。
3.  复制 `backend/.env.example` 为 `backend/.env`，并填入所有必需的环境变量。
4.  通过 SSH 登录到服务器，进入 `backend` 目录，运行 `php setup_database.php` 来创建数据表。
5.  在 Serv00 控制面板设置 Cron Job，指向 `cron/process_emails.php` 脚本，频率设置为每分钟一次。
6.  运行 `php set_webhook.php` 来设置 Telegram Bot 的 Webhook 地址。

### 前端部署 (Cloudflare Pages)

1.  Fork 本仓库或将代码推送到你自己的 GitHub/GitLab 仓库。
2.  登录 Cloudflare，进入 Pages，选择 "Create a project"，连接到你的 Git 仓库。
3.  **构建设置**:
    - **Framework preset**: `Vite`
    - **Build command**: `npm run build`
    - **Build output directory**: `dist`
    - **Root directory**: `frontend`
4.  **环境变量**: 添加 `VITE_API_URL` 变量，其值应为你的前端访问地址（例如 `https://ss.wenxiuxiu.eu.org`），尽管本项目中 API 请求被代理，但设置一下总没错。
5.  部署项目。`public/_worker.js` 会被自动识别并部署为一个边缘函数。

### Cloudflare 配置

1.  **Email Routing**: 设置你的域名，将特定地址（如 `bets@yourdomain.com`）的邮件路由到一个 Worker。
2.  **创建 Worker**: 创建一个新的 Worker，将 `frontend/public/_worker.js` 的逻辑稍作修改（主要是后端地址），或者直接使用 Pages 的 Functions 功能（`_worker.js` 文件）。但本项目更推荐后者，因为 `_worker.js` 已集成。