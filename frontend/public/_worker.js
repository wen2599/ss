// 文件名: _worker.js
// 路径: frontend/public/_worker.js
// 版本: Final with Path Correction

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const apiBaseUrl = "https://wenge.cloudns.ch";

    if (url.pathname.startsWith('/api/')) {
      // --- 关键修改在这里 ---
      // 1. 获取原始请求路径，并移除 '/api' 前缀
      const originalPath = url.pathname.substring(4);
      
      // 2. 构造新的、修正后的路径，在前面加上 /public_html
      const correctedPath = '/public_html' + originalPath;
      // --- 修改结束 ---

      // 使用修正后的路径构造目标 URL
      const destinationUrl = new URL(correctedPath + url.search, apiBaseUrl);

      const newHeaders = new Headers(request.headers);
      newHeaders.set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');
      
      const newRequest = new Request(destinationUrl, {
        method: request.method,
        headers: newHeaders,
        body: request.body,
        redirect: 'follow'
      });

      try {
        // ... (try...catch 块保持不变) ...
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