// public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // 如果请求路径以 /api/ 开头，则代理到后端服务器
    if (url.pathname.startsWith('/api/')) {
      // 你的后端 API 地址
      const backendUrl = 'https://wenge.cloudns.ch';
      
      // 创建一个新的 URL 对象，指向后端
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      // 创建一个新的请求，复制原始请求的方法、头部和主体
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // 发送到后端并返回响应
      const response = await fetch(newRequest);

      // 创建一个新的响应头，允许跨域
      const headers = new Headers(response.headers);
      headers.set('Access-Control-Allow-Origin', '*'); // 或者你的前端域名
      headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
      headers.set('Access-Control-Allow-Headers', 'Content-Type');

      return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: headers,
      });
    }

    // 对于其他请求（如 HTML, CSS, JS 文件），让 Cloudflare Pages 正常处理
    return env.ASSETS.fetch(request);
  },
};