// A more robust pass-through worker with error handling.
export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 代理 /api/ 开头的请求
    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch';
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      try {
        // --- 核心改动：使用 try...catch 包裹 fetch 请求 ---
        const response = await fetch(newRequest);
        
        // 创建一个新的响应头以允许跨域
        const headers = new Headers(response.headers);
        headers.set('Access-Control-Allow-Origin', url.origin);
        headers.set('Access-Control-Allow-Credentials', 'true');

        return new Response(response.body, {
          status: response.status,
          statusText: response.statusText,
          headers: headers
        });

      } catch (error) {
        // --- 如果 fetch 失败 (例如网络不通, SSL错误, DNS问题) ---
        // 返回一个自定义的 500 错误响应，而不是让 worker 崩溃
        console.error('Fetch to backend failed:', error);
        return new Response(
          JSON.stringify({
            message: '无法连接到后端服务，请稍后再试。',
            error: error.message,
            cause: error.cause ? error.cause.toString() : 'N/A'
          }), {
            status: 502, // 502 Bad Gateway 是一个很合适的错误码
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': url.origin,
              'Access-Control-Allow-Credentials': 'true'
            }
          }
        );
      }
    }

    // 对于非 /api/ 的请求，正常返回静态文件
    return env.fetch(request);
  }
};