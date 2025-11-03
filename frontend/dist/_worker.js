// --- Cloudflare Pages Worker (Middleware) ---
// 文件位置: /public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 1. 定义后端 API 的实际地址
    const backendHost = 'https://wenge.cloudns.ch';

    // 2. 检查请求路径是否是 API 调用
    if (url.pathname.startsWith('/api/')) {
      
      // 3. 构建指向后端服务器的 URL
      const backendUrl = `${backendHost}${url.pathname}${url.search}`;

      // 4. 创建一个新的请求对象，转发到后端
      // 我们复制原始请求的方法、头部和主体
      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: request.headers, // 复制所有原始头部
        body: request.body,
        redirect: 'follow'
      });

      // 5. (关键) 添加/修改头部信息
      //    - Host: 确保后端服务器知道它正在为哪个主机服务
      //    - Authorization: 添加用于后端验证的 API 密钥 (如果需要)
      //    - X-Forwarded-For: 传递原始客户端的 IP 地址
      backendRequest.headers.set('Host', new URL(backendHost).host);
      backendRequest.headers.set('X-Forwarded-For', request.headers.get('cf-connecting-ip'));

      // 从 Cloudflare 的环境变量中获取 API 密钥并添加到请求头
      // 您需要在 Cloudflare Pages 的设置中配置 `API_SECRET_KEY` 这个环境变量
      if (env.API_SECRET_KEY) {
          backendRequest.headers.set('Authorization', `Bearer ${env.API_SECRET_KEY}`);
      }
      
      // --- 处理 CORS 预检请求 (OPTIONS) ---
      // 虽然后端 API 也处理了，但在 Worker 层面处理可以更快响应
      if (request.method === 'OPTIONS') {
        return new Response(null, {
          status: 204,
          headers: {
            'Access-Control-Allow-Origin': url.origin, // 只允许来自您前端站点的请求
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
            'Access-Control-Max-Age': '86400', // 缓存预检结果一天
          },
        });
      }

      // 6. 发送请求到后端并获取响应
      const backendResponse = await fetch(backendRequest);

      // 7. 将后端的响应直接返回给前端浏览器
      // 我们需要创建一个新的响应以确保 CORS 头部被正确设置
      const response = new Response(backendResponse.body, backendResponse);
      response.headers.set('Access-Control-Allow-Origin', url.origin);
      response.headers.set('Access-Control-Allow-Credentials', 'true');

      return response;
    }

    // 8. 如果不是 API 请求，则正常处理，返回 Cloudflare Pages 上的静态文件 (如 index.html, css, js)
    // `env.ASSETS.fetch` 是 Cloudflare Pages 提供的固定用法
    return env.ASSETS.fetch(request);
  },
};
