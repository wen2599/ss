export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    
    // 只代理 /api/ 路径的请求
    if (url.pathname.startsWith('/api/')) {
      // 目标后端的 URL
      const backendUrl = 'https://wenge.cloudns.ch'; 
      
      const newUrl = new URL(url.pathname, backendUrl);
      
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });
      
      try {
        const response = await fetch(newRequest);
        // 必须创建新的 Response 来修改 headers
        const newResponse = new Response(response.body, response);
        
        // 添加 CORS 头，允许任何源访问
        newResponse.headers.set('Access-Control-Allow-Origin', '*');
        newResponse.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        return newResponse;

      } catch (e) {
        return new Response('Backend server error', { status: 500 });
      }
    }
    
    // 对于非 /api/ 的请求，走 Cloudflare Pages 的默认静态资源处理
    return env.ASSETS.fetch(request);
  },
};