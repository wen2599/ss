# Telegram Bot 六合彩邮件下单系统

这是一个集成了前端界面、PHP 后端 API 和 Cloudflare Email Worker 的全栈应用程序，旨在自动化处理用户的六合彩邮件下单，并通过 Telegram Bot 提供管理功能。

## 功能亮点

*   **用户认证系统**：安全的用户注册和登录功能。
*   **邮件自动接收**：通过 Cloudflare Email Worker 拦截并转发用户投注邮件至后端。
*   **后端 API 服务**：提供用户管理、邮件存储、AI 分析集成等核心业务逻辑。
*   **AI 智能分析**：计划集成 Gemini/Cloudflare AI，自动从邮件内容中提取下注信息。
*   **Telegram Bot 管理**：通过 Telegram 指令进行用户管理（如删除用户）和其他潜在的管理操作。
*   **现代化前端界面**：使用 React 和 Vite 构建，提供直观的仪表板来查看邮件和结算单。
*   **跨域解决方案**：前端通过 Cloudflare Pages Functions (`_worker.js`) 代理 API 请求，无缝解决跨域问题。

## 技术栈

**后端 (`backend/`)**
*   **语言**: PHP
*   **数据库**: MySQL (通过 PDO)
*   **依赖**: `.env` 文件进行配置管理

**Cloudflare 邮件 Worker (`cloudflare-email-worker/`)**
*   **平台**: Cloudflare Workers
*   **语言**: JavaScript
*   **依赖**: `postal-mime` (用于解析邮件)

**前端 (`frontend/`)**
*   **框架**: React.js
*   **构建工具**: Vite
*   **路由**: React Router DOM
*   **状态管理**: React Context API (用于认证)
*   **部署**: Cloudflare Pages (结合 `_worker.js` 进行 API 代理)

## 项目结构概览

```
.
├── backend/                    # PHP 后端服务
│   ├── api/                    # 公共 API 接口 (用户、邮件等)
│   ├── core/                   # 核心初始化文件 (认证、数据库、配置)
│   ├── internal/               # 内部服务 (接收邮件、Telegram Webhook)
│   ├── utils/                  # 工具类 (AI 调用、API 助手、环境变量加载)
│   ├── .env.example            # 环境变量配置模板
│   ├── .gitignore              # Git 忽略文件
│   ├── .htaccess               # Apache 重写规则 (URL 路由)
│   ├── README.md               # 后端说明
│   ├── import_tables.php       # 数据库表导入脚本
│   └── index.php               # 后端主入口和路由文件
│
├── cloudflare-email-worker/    # Cloudflare Email Worker
│   ├── README.md               # Worker 说明
│   ├── index.js                # Worker 主逻辑，处理邮件转发
│   └── wrangler.toml           # Worker 配置文件
│
├── frontend/                   # React 前端应用
│   ├── public/                 # 静态资源及 Cloudflare Pages Functions
│   │   └── _worker.js          # API 代理 Worker
│   ├── src/                    # 前端源代码
│   │   ├── components/         # 可重用 UI 组件
│   │   ├── contexts/           # React Contexts (如认证上下文)
│   │   ├── pages/              # 页面组件 (登录、注册、仪表板)
│   │   ├── services/           # API 服务调用封装
│   │   ├── App.jsx             # 应用主组件和路由定义
│   │   ├── index.css           # 全局 CSS 样式
│   │   └── main.jsx            # React 应用入口
│   ├── .gitignore              # Git 忽略文件
│   ├── README.md               # 前端说明
│   ├── index.html              # HTML 模板文件
│   ├── package.json            # npm/yarn 依赖配置文件
│   └── vite.config.js          # Vite 构建配置
│
└── worker/                     # 其他独立 Worker (根据具体用途)
    └── email_worker.js         # 独立邮件处理 Worker (可能用于特定任务)

```

## 快速开始

### 1. 后端设置

1.  进入 `backend/` 目录。
2.  将 `.env.example` 复制为 `.env`，并填写您的数据库凭据、JWT 密钥、Telegram Bot Token 和 Email Worker 密钥等信息。
3.  运行 `php import_tables.php` 创建数据库表。
4.  配置您的 Web 服务器（如 Apache 或 Nginx）以将所有请求重定向到 `backend/index.php`。
5.  确保 PHP 后端服务可以通过公共 URL 访问（例如 `https://your-backend.com`）。

### 2. Cloudflare 邮件 Worker 设置

1.  进入 `cloudflare-email-worker/` 目录。
2.  通过 Cloudflare 控制台或 `wrangler secret put` 命令设置 `BACKEND_RECEIVE_URL` (指向您的 PHP 后端 `/internal/receive_email` 接口) 和 `BACKEND_SECRET_TOKEN` (需与后端 `.env` 中的 `EMAIL_WORKER_SECRET` 匹配)。
3.  将此 Worker 部署到 Cloudflare，并配置 Cloudflare Email Routing 以将接收到的邮件转发到此 Worker。

### 3. 前端设置

1.  进入 `frontend/` 目录。
2.  安装依赖：`npm install` 或 `yarn install`。
3.  本地开发运行：`npm run dev` 或 `yarn dev`。
4.  构建生产版本：`npm run build` 或 `yarn build`。
5.  将 `frontend/dist` 目录部署到 Cloudflare Pages，并确保 `frontend/public/_worker.js` 作为 Pages Function 正确配置，以代理 `/api/` 请求到您的后端 URL (`https://wenge.cloudns.ch`)。

## 使用指南

*   **注册和登录**：用户通过前端界面注册账户并登录。
*   **发送邮件下单**：用户向预设的邮箱地址发送包含六合彩投注信息的邮件。
*   **邮件处理**：Cloudflare Email Worker 接收邮件并发送给后端。
*   **AI 分析**：后端服务调用 AI 分析邮件内容，提取下注数据并存储。
*   **查看数据**：登录前端仪表板查看收到的邮件原文和处理后的结算表单（待开发）。
*   **管理**：通过 Telegram Bot 向您的机器人发送特定命令来执行管理操作，例如 `/delete_user user@example.com`。
