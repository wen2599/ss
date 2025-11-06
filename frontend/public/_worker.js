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

    // 2. PHP 脚本请求 (webhook.php 和我们的测试文件)
    if (pathname === '/webhook.php' || pathname === '/webhook_test.php' || pathname === '/test_final.php') {
      const backendUrl = new URL("https://ss.wenxiuxiu.eu.org" + pathname);
      return await proxyRequest(request, backendUrl.toString());
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
    const requestOptions = {
      method: request.method,
      headers: request.headers,
      redirect: 'follow',
    };

    // For GET or HEAD requests, the body must be null.
    // For other methods, we pass the body and add the 'duplex' property for streaming.
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      requestOptions.body = request.body;
      requestOptions.duplex = 'half'; // CRITICAL FIX for streaming body errors
    }

    const backendRequest = new Request(backendUrl, requestOptions);

    const backendResponse = await fetch(backendRequest);
    
    const response = new Response(backendResponse.body, backendResponse);

    // Add CORS headers to allow the frontend to access the response
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
