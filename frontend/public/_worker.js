// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 后端 API 的地址
    const backendUrl = "https://wenge.cloudns.ch";

    // 如果请求路径以 /api/ 开头, 我们将其代理到后端
    if (url.pathname.startsWith('/api/')) {
      // 创建一个新的 URL 指向后端
      // 例如：/api/?action=login -> https://wenge.cloudns.ch/index.php?action=login
      const newPath = "/index.php" + url.search;
      const backendApiUrl = new URL(newPath, backendUrl);
      
      // 创建一个新的请求对象，除了 URL 其他都和原始请求一样
      const newRequest = new Request(backendApiUrl, request);

      // 添加一些必要的头信息
      newRequest.headers.set('Host', new URL(backendUrl).hostname);
      newRequest.headers.set('Origin', url.origin); // 将前端源地址传给后端

      // 发送到后端并返回响应
      return fetch(newRequest);
    }

    // 对于其他所有请求 (例如 /, /index.html, /src/main.jsx 等),
    // 让 Cloudflare Pages 默认的静态资源处理器来处理
    return env.ASSETS.fetch(request);
  },
};