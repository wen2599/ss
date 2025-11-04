export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    
    // 设置CORS头
    const corsHeaders = {
      'Access-Control-Allow-Origin': 'https://ss.wenxiuxiu.eu.org',
      'Access-Control-Allow-Methods': 'GET, HEAD, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
      'Access-Control-Allow-Credentials': 'true'
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
      
      console.log('Proxying to:', backendUrl);
      
      try {
        const response = await fetch(backendUrl, {
          method: request.method,
          headers: {
            'Content-Type': 'application/json',
            ...(request.headers.get('Authorization') && {
              'Authorization': request.headers.get('Authorization')
            })
          },
          body: request.method !== 'GET' && request.method !== 'HEAD' ? await request.text() : undefined
        });

        // 创建修改后的响应
        const modifiedResponse = new Response(response.body, {
          status: response.status,
          statusText: response.statusText,
          headers: {
            ...Object.fromEntries(response.headers),
            ...corsHeaders
          }
        });

        return modifiedResponse;
      } catch (error) {
        console.error('Proxy error:', error);
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