// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 您的后端 API 地址
    const backendUrl = "https://wenge.cloudns.ch";

    // 如果请求路径以 /api/ 开头, 我们将其代理到后端
    if (url.pathname.startsWith('/api/')) {
      // 构造指向后端 PHP 脚本的 URL
      const newPath = "/index.php" + url.search;
      const backendApiUrl = new URL(newPath, backendUrl);
      
      // 创建一个新的请求初始化对象
      // 这可以正确处理 GET, POST 等所有类型的请求体
      const newRequest = new Request(backendApiUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body, // 保持原始请求体
        duplex: 'half',   // 【关键修复】添加此属性以兼容新的 Fetch API 标准
      });

      // 设置正确的 Host 头，以便后端服务器能正确识别
      newRequest.headers.set('Host', new URL(backendUrl).hostname);

      // 将请求发送到后端并返回响应
      return fetch(newRequest);
    }

    // 对于所有其他非 /api/ 的请求 (如 HTML/CSS/JS 静态资源),
    // 交由 Cloudflare Pages 默认的静态资源处理器来处理
    return env.ASSETS.fetch(request);
  },
};