export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // 我们只代理对 /api/ 路径的请求
    if (url.pathname.startsWith('/api/')) {
      // 你的后端 API 地址
      const backendUrl = 'https://wenge.cloudns.ch';

      // 构建指向后端的新 URL
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      // --- 关键修复在这里 ---
      // 创建一个新的请求头对象，复制原始请求头
      const newHeaders = new Headers(request.headers);
      // 确保 Host 头指向后端服务器，这对于某些服务器配置很重要
      newHeaders.set('Host', new URL(backendUrl).host);
      
      // 创建一个新的请求对象用于转发
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: newHeaders,
        body: request.body, // 传递原始请求体
        // 对于 POST/PUT 等带 body 的请求，必须指定 duplex
        ...(request.method !== 'GET' && request.method !== 'HEAD' && { duplex: 'half' }), 
        redirect: 'follow'
      });
      // ----------------------

      try {
        // 向后端服务器发起请求
        const response = await fetch(newRequest);
        return response;
      } catch (e) {
        // 如果后端无法访问，返回一个更明确的 503 错误
        return new Response('Backend server is unavailable.', { status: 503 });
      }
    }

    // 如果请求的不是 /api/ 路径，则提供 Pages 的静态资源
    return env.ASSETS.fetch(request);
  },
};
