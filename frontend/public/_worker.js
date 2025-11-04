// Cloudflare Pages Worker 解决跨域问题
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    
    // 代理 API 请求到后端服务器
    if (url.pathname.startsWith('/api/')) {
      const backendUrl = `https://wenge.cloudns.ch${url.pathname}${url.search}`;
      
      const modifiedRequest = new Request(backendUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body
      });
      
      try {
        const response = await fetch(modifiedRequest);
        const modifiedResponse = new Response(response.body, response);
        
        // 设置 CORS 头部
        modifiedResponse.headers.set('Access-Control-Allow-Origin', 'https://ss.wenxiuxiu.eu.org');
        modifiedResponse.headers.set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        modifiedResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        return modifiedResponse;
      } catch (error) {
        return new Response(JSON.stringify({ error: '后端请求失败' }), {
          status: 500,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': 'https://ss.wenxiuxiu.eu.org'
          }
        });
      }
    }
    
    // 静态资源请求
    return await env.ASSETS.fetch(request);
  }
};