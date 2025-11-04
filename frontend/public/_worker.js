export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    
    // 设置CORS头
    const corsHeaders = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, HEAD, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    };

    // 处理预检请求
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        headers: corsHeaders
      });
    }

    // 代理API请求到后端
    if (url.pathname.startsWith('/api/')) {
      const backendUrl = `https://wenge.cloudns.ch${url.pathname}${url.search}`;
      
      try {
        const response = await fetch(backendUrl, {
          method: request.method,
          headers: {
            'Content-Type': 'application/json',
            ...(request.headers.get('Authorization') && {
              'Authorization': request.headers.get('Authorization')
            })
          },
          body: request.method !== 'GET' ? await request.text() : undefined
        });

        const modifiedResponse = new Response(response.body, response);
        
        // 添加CORS头
        Object.entries(corsHeaders).forEach(([key, value]) => {
          modifiedResponse.headers.set(key, value);
        });

        return modifiedResponse;
      } catch (error) {
        return new Response(JSON.stringify({
          success: false,
          error: 'Backend service unavailable'
        }), {
          status: 503,
          headers: {
            'Content-Type': 'application/json',
            ...corsHeaders
          }
        });
      }
    }

    // 对于其他请求，继续正常处理
    return env.ASSETS.fetch(request);
  },
};
