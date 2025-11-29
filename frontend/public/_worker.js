// File: frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Only proxy API requests
    if (url.pathname.startsWith('/api/')) {
      const endpoint = url.pathname.substring(5);
      const backendUrl = `https://wenge.cloudns.ch/index.php?endpoint=${endpoint}${url.search}`;

      // 【关键修复】创建新的 Headers 对象并删除可能导致问题的头
      const newHeaders = new Headers(request.headers);
      newHeaders.delete('content-length');   // 删除长度头，让 fetch 重新计算
      newHeaders.delete('content-encoding'); // 删除编码头
      newHeaders.delete('host');             // 删除 Host 头

      const backendRequestInit = {
        method: request.method,
        headers: newHeaders,
        // 对于非 GET 请求，克隆 body 并转为 ArrayBuffer
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