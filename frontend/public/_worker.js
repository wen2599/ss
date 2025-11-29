// File: frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Only proxy API requests
    if (url.pathname.startsWith('/api/')) {
      const endpoint = url.pathname.substring(5);
      const backendUrl = `https://wenge.cloudns.ch/index.php?endpoint=${endpoint}${url.search}`;

      // 复制原有 header
      const newHeaders = new Headers(request.headers);
      
      // 【关键修复】删除可能导致 PHP 读取不到 body 的头
      // 必须删除 content-length，让 fetch 根据 body 自动重新计算
      newHeaders.delete('content-length');
      newHeaders.delete('content-encoding'); 
      newHeaders.delete('host'); 
      newHeaders.delete('connection');

      const backendRequestInit = {
        method: request.method,
        headers: newHeaders,
        // 对于非 GET 请求，必须克隆 body 并转为 ArrayBuffer
        // 这样可以确保发送的是完整的二进制数据，避免编码问题
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