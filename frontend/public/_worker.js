// frontend/public/_worker.js (最终代理版本)

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendUrl = 'https://wenge.cloudns.ch'; // 您的后端地址

    // --- 路由 1: 代理来自浏览器的 API 请求 ---
    if (url.pathname.startsWith('/api/')) {
      // 构造指向后端 API 的 URL (例如: https://wenge.cloudns.ch/api.php)
      const newUrl = new URL(backendUrl + '/api.php' + url.search);

      // 创建一个新请求，转发到后端
      const newRequest = new Request(newUrl, request);
      
      const response = await fetch(newRequest);
      
      // 添加 CORS 头，允许前端页面访问
      const newResponse = new Response(response.body, response);
      newResponse.headers.set('Access-Control-Allow-Origin', url.origin);
      newResponse.headers.set('Access-Control-Allow-Methods', 'GET, OPTIONS');
      newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type');
      return newResponse;
    }

    // --- 路由 2: 代理来自 Telegram 的 Webhook 请求 ---
    if (url.pathname.startsWith('/webhook_proxy')) {
      // 构造指向后端 webhook.php 的 URL，并附带上 secret 参数
      // 例如: https://wenge.cloudns.ch/webhook.php?secret=YOUR_SECRET
      const newUrl = new URL(backendUrl + '/webhook.php' + url.search);

      // 创建一个新请求，复制 Telegram 发来的方法、头部和主体数据
      const newRequest = new Request(newUrl, request);

      // 将请求发送到您的后端，并直接返回 Telegram 需要的响应
      return fetch(newRequest);
    }

    // --- 默认行为: 提供静态网站资源 ---
    return env.ASSETS.fetch(request);
  },
};