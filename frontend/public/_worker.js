// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      const BACKEND_URL = 'https://wenge.cloudns.ch';
      const WORKER_SECRET = '816429fb-1649-4e48-9288-7629893311a6';

      const newPathname = url.pathname.substring(4);
      const targetUrl = new URL(newPathname + url.search, BACKEND_URL);
      
      // --- START: 关键修复 ---
      // 复制原始请求的 headers 和 method
      const newHeaders = new Headers(request.headers);
      const newMethod = request.method;

      // 检查请求是否有 body
      const body = (newMethod === 'POST' || newMethod === 'PUT') ? await request.blob() : null;

      // 创建一个新的请求对象
      // 对于有 body 的请求，必须指定 duplex: 'half'
      const newRequestInit = {
        method: newMethod,
        headers: newHeaders,
        body: body,
        ...(body && { duplex: 'half' }), // 如果有 body，则添加 duplex 属性
      };
      // --- END: 关键修复 ---

      const newRequest = new Request(targetUrl, newRequestInit);

      newRequest.headers.set('X-Worker-Secret', WORKER_SECRET);
      
      return fetch(newRequest);
    }

    return env.ASSETS.fetch(request);
  },
};