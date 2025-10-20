# README

## 最终版前后端分离项目

### 描述
这是一个经过全面重构和优化的全栈Web应用程序，前端使用 Vue.js，后端使用原生 PHP。我们实现了一个统一的 API 入口 (`index.php`)，一个强大的引导文件 (`bootstrap.php`)，以及一个更清晰、更安全的项目结构。

### 部署指南 (Nginx)

**1. 文件上传**
将所有文件上传到您的服务器。`frontend/dist` 目录包含所有编译好的前端静态资源，后端 PHP 文件位于 `backend` 目录。

**2. Nginx 配置**
使用以下配置。这个配置将Web根目录指向 `frontend/dist`，并设置了一个代理，将所有以 `/api/` 开头的请求转发到后端的 `index.php`。

```nginx
server {
    listen 80;
    server_name your_domain.com;
    root /path/to/your/project/frontend/dist; # 指向前端构建输出目录

    index index.html;

    # 处理前端路由 (History 模式)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 代理
    location /api/ {
        # 重写请求，将 /api/index.php?endpoint=xyz -> /index.php?endpoint=xyz
        rewrite ^/api/(.*)$ /$1 break;

        # 代理到后端的 PHP-FPM
        proxy_pass http://127.0.0.1:9000; # 确保这是您的 PHP-FPM 监听地址
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # 包含标准的 FastCGI 参数
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /path/to/your/project/backend/index.php; # 指向后端的统一入口
        fastcgi_param SCRIPT_NAME /index.php; # 告诉 PHP 脚本名称
        fastcgi_param DOCUMENT_ROOT /path/to/your/project/backend; # 后端根目录
    }

    # 处理静态文件缓存
    location ~* \.(?:jpg|jpeg|gif|png|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public";
    }
}
```

**3. .env 文件**
在项目的根目录下创建一个 `.env` 文件，并填入以下凭证：
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USER=your_db_user
DB_PASSWORD=your_db_password

TELEGRAM_BOT_TOKEN=your_telegram_bot_token_here
TELEGRAM_ADMIN_CHAT_ID=your_telegram_chat_id_here

GEMINI_API_KEY=your_gemini_api_key_here
CLOUDFLARE_ACCOUNT_ID=your_cloudflare_account_id_here
CLOUDFLARE_API_TOKEN=your_cloudflare_api_token_here
```

**4. 初始化数据库**
通过命令行在 `backend` 目录中执行 `php initialize_database.php` 来创建数据库表。

### 项目结构 (最终版)
```
.env
README.md

frontend/
  dist/             # 前端静态文件 (Web根目录)
  src/
    components/
    router/
    views/
    App.vue
    main.js
    api.js
  package.json
  vite.config.js

backend/
  index.php           # 所有API请求的统一入口
  bootstrap.php       # 核心：加载配置、函数、会话、数据库
  db_operations.php   # 数据库操作函数
  database_schema.sql # 数据库结构
  initialize_database.php # 数据库初始化脚本

  # --- 辅助模块 (全部由 bootstrap.php 加载) ---
  api_curl_helper.php
  cloudflare_ai_helper.php
  gemini_ai_helper.php
  telegram_helpers.php
  user_state_manager.php
  email_handler.php
  process_email_ai.php

  # --- 日志文件 ---
  backend.log
```

### API 端点 (通过 index.php)
所有API请求都发送到 `GET /api/index.php?endpoint=<endpoint_name>`。

- `register_user` (POST)
- `login_user` (POST)
- `logout_user` (POST)
- `check_session` (GET)
- `get_emails` (GET)
- `get_bills` (GET)
- `delete_bill` (DELETE)
- `process_email_ai` (POST)
- `get_lottery_results` (GET)
- `telegram_webhook` (POST) - Bot 的 Webhook 入口
