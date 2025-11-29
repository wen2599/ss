// File: frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      const endpoint = url.pathname.substring(5);
      const backendUrl = `https://wenge.cloudns.ch/index.php?endpoint=${endpoint}${url.search}`;

      const newHeaders = new Headers(request.headers);
      newHeaders.delete('content-length');
      newHeaders.delete('content-encoding');
      newHeaders.delete('host');

      const backendRequestInit = {
        method: request.method,
        headers: newHeaders,
        // 简化 Body 处理
        body: request.method !== 'GET' && request.body ? request.body : null,
        redirect: 'follow'
      };

      try {
        return await fetch(backendUrl, backendRequestInit);
      } catch (e) {
        return new Response(`Proxy Error: ${e.message}`, { status: 503 });
      }
    }

    return env.ASSETS.fetch(request);
  },
};