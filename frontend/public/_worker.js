// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    // --- 路由逻辑: 根据路径决定如何代理 ---
    
    // 1. API 请求 (/api/...)
    if (pathname.startsWith('/api/')) {
      const backendUrl = new URL("https://ss.wenxiuxiu.eu.org/api.php");
      backendUrl.search = url.search; // 复制查询参数
      return await proxyRequest(request, backendUrl.toString());
    }

    // 2. Telegram Webhook 请求 (/webhook.php)
    if (pathname === '/webhook.php') {
      const backendUrl = "https://ss.wenxiuxiu.eu.org/webhook.php";
      return await proxyRequest(request, backendUrl);
    }

    // 3. 对于其他所有请求，由 Pages 處理靜態資源
    return env.ASSETS.fetch(request);
  },
};


/**
 * 辅助函数：代理请求到后端
 * @param {Request} request 原始请求
 * @param {string} backendUrl 后端目标 URL
 * @returns {Promise<Response>}
 */
async function proxyRequest(request, backendUrl) {
  try {
    const backendRequest = new Request(backendUrl, {
      method: request.method,
      headers: request.headers,
      body: request.body,
      redirect: 'follow'
    });

    const backendResponse = await fetch(backendRequest);

    // --- 健壮的响应处理 ---
    const originalBodyText = await backendResponse.text();
    const originalHeaders = backendResponse.headers;
    const originalStatus = backendResponse.status;
    const contentType = originalHeaders.get('content-type') || '';

    if (!contentType.includes('application/json') && originalStatus >= 400) {
      const errorPayload = {
        success: false,
        message: "后端错误或无效响应。",
        details: {
          backend_status: originalStatus,
          backend_content_type: contentType,
          backend_response_snippet: originalBodyText.substring(0, 500)
        }
      };
      return new Response(JSON.stringify(errorPayload), {
        status: 502,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
      });
    }

    // 创建新响应以附加CORS头
    const response = new Response(originalBodyText, {
      status: originalStatus,
      headers: originalHeaders
    });

    response.headers.set('Access-Control-Allow-Origin', '*');
    response.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token');
    
    return response;

  } catch (error) {
    const errorResponse = {
      success: false,
      message: 'Cloudflare Worker 无法连接到后端服务器。',
      error: { name: error.name, message: error.message }
    };
    return new Response(JSON.stringify(errorResponse), {
      status: 503,
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
    });
  }
}
