// File: frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Only proxy API requests
    if (url.pathname.startsWith('/api/')) {
      const endpoint = url.pathname.substring(5);
      const backendUrl = `https://wenge.cloudns.ch/index.php?endpoint=${endpoint}${url.search}`;

      // 创建新的 Headers 对象
      const newHeaders = new Headers(request.headers);
      
      // 【关键修复】删除可能导致 PHP 读取不到 body 的头
      // 这强制 fetch 根据实际内容重新计算 Content-Length
      newHeaders.delete('content-length');
      newHeaders.delete('content-encoding'); 
      newHeaders.delete('host'); 

      const backendRequestInit = {
        method: request.method,
        headers: newHeaders,
        // 对于非 GET 请求，克隆 body 并转换为 ArrayBuffer，防止流被锁定
        body: request.method !== 'GET' && request.body ? await request.clone().arrayBuffer() : null,
        duplex: 'half'
      };

      try {
        const backendRequest = new Request(backendUrl, backendRequestInit);
        return await fetch(backendRequest);
      } catch (e) {
        return new Response(`Backend server is unreachable: ${e.message}`, { status: 503 });
      }
    }

    // Serve static assets
    return env.ASSETS.fetch(request);
  },
};