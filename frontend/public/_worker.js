// frontend/public/_worker.js (Final Version, handles POST body correctly)

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendUrl = 'https://wenge.cloudns.ch'; // 您的后端地址

    // --- 路由 1: 代理来自浏览器的 API GET 请求 ---
    if (url.pathname === '/api/get_results') {
      const newUrl = new URL(backendUrl + '/api.php' + url.search);
      // 对于简单的 GET 请求，可以直接 fetch newUrl
      const response = await fetch(newUrl);
      
      // 创建响应副本以添加 CORS 头
      const newResponse = new Response(response.body, response);
      newResponse.headers.set('Access-Control-Allow-Origin', url.origin);
      newResponse.headers.set('Access-Control-Allow-Methods', 'GET, OPTIONS');
      newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type');
      return newResponse;
    }

    // --- 路由 2: 代理来自 Telegram 的 Webhook POST 请求 ---
    // 这个判断捕获所有指向 /webhook_proxy 的请求
    if (url.pathname === '/webhook_proxy') {
      // 构造指向后端 webhook.php 的 URL，并附带上 secret 参数
      const newUrl = new URL(backendUrl + '/webhook.php' + url.search);

      // 关键修复：创建一个新的 Request 对象，并将原始 request 作为其 init 对象。
      // 这种方法可以确保 POST body, method, headers 等所有内容都被完整复制并转发。
      const newRequest = new Request(newUrl, request);

      // 将构建好的新请求发送到您的后端，并直接返回响应给 Telegram
      return fetch(newRequest);
    }

    // --- 默认行为: 提供静态网站资源 ---
    return env.ASSETS.fetch(request);
  },
};