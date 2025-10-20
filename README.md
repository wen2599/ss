# README

## 全栈Web应用项目

### 描述
这是一个经过全面重构和优化的全栈Web应用程序，前端使用React，后端使用原生PHP。项目采用统一的API入口(`index.php`)、强大的引导文件(`bootstrap.php`)以及一个清晰、安全的项目结构。

### 部署指南 (Nginx) - 已修正

**1. 文件上传**
将所有文件上传到您的服务器。`frontend/build`目录包含所有编译好的前端静态资源，后端PHP文件位于`backend`目录。

**2. Nginx 配置 (重要更新)**
请使用以下经过修正的Nginx配置。此配置能正确处理前端路由，并将API请求无误地交由后端的PHP-FPM执行。

```nginx
server {
    listen 80;
    server_name your_domain.com; # 替换为您的域名

    # 1. 前端应用配置
    location / {
        # 指向您前端构建输出的目录 (通常是 build 或 dist)
        root /path/to/your/project/frontend/build;
        # 此设置确保在使用 history 模式路由时，刷新页面不会导致404
        try_files $uri $uri/ /index.html;
    }

    # 2. 后端 API 代理配置
    # 所有以 /api/ 开头的请求都会被这里处理
    location /api/ {
        # 'alias' 指令将URL路径映射到服务器上的物理路径。
        # 例如, 请求 /api/index.php 将会执行 /path/to/your/project/backend/index.php 文件。
        alias /path/to/your/project/backend/;

        # 确保所有请求都最终指向 index.php 前端控制器
        try_files $uri $uri/ /api/index.php?$query_string;

        # 嵌套 location 块，专门处理 PHP 文件的执行
        location ~ \.php$ {
            include fastcgi_params;

            # SCRIPT_FILENAME 是最重要的参数，它告诉PHP-FPM要执行哪个文件。
            # $request_filename 变量会持有由 alias 指令转换后的正确文件路径。
            fastcgi_param SCRIPT_FILENAME $request_filename;

            # 根据您的服务器环境，选择使用 sock 文件或 TCP 端口
            # 示例 (Ubuntu with PHP 8.1):
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            # 或者使用 TCP 端口:
            # fastcgi_pass 127.0.0.1:9000;

            fastcgi_index index.php;
        }
    }

    # 可选: 为静态资源设置浏览器缓存
    location ~* \.(?:jpg|jpeg|gif|png|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public";
    }
}
```

**3. .env 文件**
在项目的根目录下创建一个`.env`文件，并填入以下凭证：
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USER=your_db_user
DB_PASSWORD=your_db_password

TELEGRAM_BOT_TOKEN=your_telegram_bot_token_here
TELEGRAM_WEBHOOK_SECRET=your_secret_string_for_webhook
TELEGRAM_ADMIN_ID=your_telegram_admin_id
LOTTERY_CHANNEL_ID=your_lottery_channel_id

GEMINI_API_KEY=your_gemini_api_key_here
CLOUDFLARE_ACCOUNT_ID=your_cloudflare_account_id_here
CLOUDFLARE_API_TOKEN=your_cloudflare_api_token_here
```

**4. 初始化数据库**
通过命令行在`backend`目录中执行`php initialize_database.php`来创建数据库表。

### 项目结构
```
.env
README.md
frontend/
  build/            # 前端静态文件 (Web根目录)
  src/
    ...
    api.js
backend/
  index.php         # 所有API请求的统一入口
  bootstrap.php     # 核心：加载配置、函数、会话、数据库
  ... (其他PHP文件)
```
