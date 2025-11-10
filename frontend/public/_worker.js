// File: frontend/public/_worker.js (修复版)

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Only proxy API requests
    if (url.pathname.startsWith('/api/')) {
      const endpoint = url.pathname.substring(5);
      const backendUrl = `https://wenge.cloudns.ch/index.php?endpoint=${endpoint}${url.search}`;

      // 修复：创建新的请求对象，避免流式 body 问题
      const backendRequestInit = {
        method: request.method,
        headers: new Headers(request.headers),
        // 对于非 GET 请求，克隆 body 以避免流式问题
        body: request.method !== 'GET' && request.body ? await request.clone().arrayBuffer() : null,
        // 添加 duplex 设置
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
