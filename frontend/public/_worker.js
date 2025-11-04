export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 1. 代理 API 请求
    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch';
      const newUrl = new URL(backendUrl + url.pathname + url.search);
      const newRequest = new Request(newUrl, request);

      try {
        const response = await fetch(newRequest);
        const headers = new Headers(response.headers);
        headers.set('Access-Control-Allow-Origin', url.origin);
        return new Response(response.body, {
          status: response.status,
          statusText: response.statusText,
          headers: headers,
        });
      } catch (error) {
        console.error('Fetch to backend failed:', error);
        return new Response(
          JSON.stringify({ message: 'Backend service is unavailable.' }),
          {
            status: 502,
            headers: { 'Content-Type': 'application/json' },
          }
        );
      }
    }

    // 2. 处理所有其他请求（主要是静态资源）
    try {
      // 尝试让 Cloudflare Pages 的静态资源服务处理请求
      const response = await env.fetch(request);
      
      // 检查静态资源是否存在。如果不存在，Pages 可能会返回一个表示404的响应
      // 我们可以直接返回这个响应，而不是让worker崩溃
      if (response.status === 404) {
          console.warn(`Static asset not found: ${url.pathname}`);
          // 可以返回一个自定义的404页面或简单的文本
          return new Response('Not Found', { status: 404 });
      }

      return response;

    } catch (error) {
      // 如果 env.fetch 本身就抛出了异常
      console.error('Error fetching static asset:', error);
      return new Response('An internal error occurred while fetching assets.', { status: 500 });
    }
  }
};