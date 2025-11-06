// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 我们只代理 /api/ 开头的请求
    if (url.pathname.startsWith('/api/')) {
      // 1. 构造后端 URL
      // 将前端的 /api/?action=... 转换为后端的 /api.php?action=...
      const backendUrl = new URL(url.pathname.replace('/api/', '/api.php?'), "https://ss.wenxiuxiu.eu.org");
      backendUrl.search = url.search; // 复制所有查询参数

      try {
        // 2. 创建一个忠实于原始请求的后端请求
        //    - 复制原始方法 (GET, POST, etc.)
        //    - 复制原始请求体 (body)
        //    - 复制重要的头部信息
        const backendRequest = new Request(backendUrl.toString(), {
          method: request.method,
          headers: request.headers, // 传递原始头部
          body: request.body,
          redirect: 'follow' // 遵循重定向
        });

        // 3. 发送到后端
        const backendResponse = await fetch(backendRequest);

        // 4. 创建一个新的响应，以便我们可以修改头部 (添加CORS)
        //    直接修改 backendResponse.headers 是不允许的
        const response = new Response(backendResponse.body, backendResponse);

        // --- 关键: 添加CORS头部，允许前端调用 ---
        response.headers.set('Access-Control-Allow-Origin', '*'); // 允许任何来源
        response.headers.set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
        response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        
        // 5. [增强] 检查后端是否真的返回了JSON
        // 如果后端崩溃并返回HTML错误页，我们将其转换为JSON错误
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const originalText = await backendResponse.text(); // 读取原始文本
            const errorResponse = {
                success: false,
                message: "Backend did not return valid JSON. This often means a server-side error occurred.",
                backend_response: originalText.substring(0, 500) // 截取一部分后端响应用于调试
            };
            return new Response(JSON.stringify(errorResponse), {
                status: 502, // Bad Gateway
                headers: {
                    'Content-Type': 'application/json',
                    'Access-Control-Allow-Origin': '*'
                }
            });
        }
        
        return response;

      } catch (error) {
        // 如果 fetch 本身抛出异常 (例如DNS解析失败, 网络不通)
        const errorResponse = {
          success: false,
          message: 'Cloudflare Worker failed to fetch the backend API.',
          error: {
              name: error.name,
              message: error.message,
          }
        };
        return new Response(JSON.stringify(errorResponse), {
          status: 503, // Service Unavailable
          headers: {
            'Content-Type': 'application/json',
            'Access-control-allow-origin': '*'
          }
        });
      }
    }

    // 对于非 /api/ 的请求，交由 Cloudflare Pages 处理静态资源
    return env.ASSETS.fetch(request);
  },
};
