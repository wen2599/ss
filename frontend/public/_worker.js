// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 我们只代理 /api/ 开头的请求
    // 这将处理像 /api/?action=register 这样的请求
    if (url.pathname.startsWith('/api/')) {
      
      // --- 关键修复: 根据您的反馈，构造正确的后端URL ---
      // 服务器的Web根目录是'backend'文件夹，所以路径就是 /api.php
      const backendUrl = new URL("https://ss.wenxiuxiu.eu.org/api.php");
      backendUrl.search = url.search; // 安全地复制所有查询参数 (?action=register, etc.)

      try {
        const backendRequest = new Request(backendUrl.toString(), {
          method: request.method,
          headers: request.headers,
          body: request.body,
          redirect: 'follow'
        });

        const backendResponse = await fetch(backendRequest);

        // --- 健壮的响应处理 ---
        // 这个逻辑确保我们总是向前端返回有效的JSON，
        // 即使后端服务器返回一个HTML错误页面。
        const originalBodyText = await backendResponse.text();
        const originalHeaders = backendResponse.headers;
        const originalStatus = backendResponse.status;
        const contentType = originalHeaders.get('content-type') || '';

        // 如果后端返回的不是JSON (例如一个 502 或 404 HTML错误页面),
        // 我们会拦截它并创建一个格式正确的JSON错误响应。
        if (!contentType.includes('application/json')) {
          const errorPayload = {
            success: false,
            message: "后端没有返回有效的JSON。这通常表示发生了服务器端错误。",
            details: {
              backend_status: originalStatus,
              backend_content_type: contentType,
              backend_response_snippet: originalBodyText.substring(0, 500) // 包含一小段响应内容用于调试
            }
          };
          return new Response(JSON.stringify(errorPayload), {
            status: 502, // Bad Gateway，因为代理到后端的连接未能获取JSON
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            }
          });
        }

        // 如果后端返回了JSON，我们创建一个新响应来添加CORS头。
        const response = new Response(originalBodyText, {
          status: originalStatus,
          headers: originalHeaders
        });

        response.headers.set('Access-Control-Allow-Origin', '*');
        response.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        return response;

      } catch (error) {
        // 如果 fetch 调用本身失败 (例如 DNS 错误, 网络问题)
        const errorResponse = {
          success: false,
          message: 'Cloudflare Worker无法连接到后端API。',
          error: { name: error.name, message: error.message }
        };
        return new Response(JSON.stringify(errorResponse), {
          status: 503, // Service Unavailable
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
        });
      }
    }

    // 对于非/api/的请求，由Pages服务静态资源。
    return env.ASSETS.fetch(request);
  },
};
