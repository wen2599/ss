// 文件名: _worker.js
// 路径: frontend/public/_worker.js
// 版本: Final with User-Agent spoofing

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const apiBaseUrl = "https://wenge.cloudns.ch";

    if (url.pathname.startsWith('/api/')) {
      const newPath = url.pathname.substring(4);
      const destinationUrl = new URL(newPath + url.search, apiBaseUrl);

      // --- 关键修改在这里 ---
      // 1. 复制原始请求的 Headers
      const newHeaders = new Headers(request.headers);

      // 2. 设置一个通用的浏览器 User-Agent
      // 这会让我们的 Worker 请求看起来像一个普通的 Chrome 浏览器，
      // 从而有很大几率绕过服务器端的防火墙或机器人检测。
      newHeaders.set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');
      
      // (可选) 有些服务器还会检查 Host 头，我们也可以伪装一下
      // newHeaders.set('Host', new URL(apiBaseUrl).host);
      // --- 修改结束 ---

      const newRequest = new Request(destinationUrl, {
        method: request.method,
        headers: newHeaders, // 使用我们修改过的新 Headers
        body: request.body,
        redirect: 'follow'
      });

      try {
        const backendResponse = await fetch(newRequest);
        
        const responseHeaders = new Headers(backendResponse.headers);
        responseHeaders.set('Access-Control-Allow-Origin', '*');
        responseHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        responseHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: responseHeaders
        });

      } catch (error) {
        console.error(`Proxy failed for ${destinationUrl.toString()}:`, error);
        return new Response(`API proxy failed: ${error.message}`, { status: 502 });
      }
    }
    
    return env.ASSETS.fetch(request);
  },
};