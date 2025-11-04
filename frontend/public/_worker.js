export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 只处理 /api/ 开头的请求
    if (url.pathname.startsWith('/api/')) {
      // 后端服务器的地址
      const backendUrl = 'https://wenge.cloudns.ch';
      
      // 创建一个新的 URL 指向后端
      const newUrl = new URL(backendUrl + url.pathname + url.search);
      
      // 创建一个新的请求，并直接转发
      // 注意：这里我们简化了 new Request 的创建，直接传入原始 request 对象
      // 这样可以更好地保留原始请求的各种属性
      const newRequest = new Request(newUrl, request);

      try {
        return await fetch(newRequest);
      } catch (error) {
        // 如果后端请求失败，返回一个明确的错误
        // 这部分保留，因为它很有用
        return new Response(JSON.stringify({ error: `Backend fetch failed: ${error.message}` }), {
          status: 502,
          headers: { 'Content-Type': 'application/json' }
        });
      }
    }

    // 对于所有非 /api/ 的请求，
    // 直接让 Cloudflare Pages 的原生静态资源处理器接管。
    // 这是最标准、最不容易出错的做法。
    return env.fetch(request);
  }
};