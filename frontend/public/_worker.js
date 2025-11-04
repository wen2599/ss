// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 检查请求路径是否以 /api/ 开头
    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch'; // 你的后端地址

      let newPath;

      // === 核心修改在这里 ===
      // 我们建立一个从友好 URL 到实际 PHP 文件的映射
      // 现在只有一个路由，但未来可以扩展
      if (url.pathname === '/api/get_results') {
        newPath = '/api.php';
      } else {
        // 如果有其他 /api/ 请求，可以添加更多 else if
        // 或者返回一个 404 错误
        return new Response('API endpoint not found.', { status: 404 });
      }

      // 构造指向后端实际文件的完整 URL
      const newUrl = new URL(backendUrl + newPath + url.search);

      // 创建一个新的请求对象，复制原始请求的所有属性
      const newRequest = new Request(newUrl, request);

      try {
        // 向后端发送请求
        const response = await fetch(newRequest);

        // 创建一个可修改的响应副本，以便我们添加 CORS 头
        const newResponse = new Response(response.body, response);

        // 动态设置 Access-Control-Allow-Origin 为请求来源
        // 这样最安全，也最灵活
        newResponse.headers.set('Access-Control-Allow-Origin', url.origin);
        newResponse.headers.set('Vary', 'Origin'); // 告诉浏览器缓存根据 Origin 头变化
        newResponse.headers.set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type');
        
        return newResponse;

      } catch (e) {
        // 如果 Worker 无法连接到后端服务器 (DNS, SSL, 超时等问题)
        // 返回一个 502 Bad Gateway 错误，并附带错误信息
        return new Response('Backend server fetch failed: ' + e.message, { status: 502 });
      }
    }

    // 对于所有非 /api/ 的请求 (例如 HTML, CSS, JS 文件)，
    // 让 Cloudflare Pages 正常提供静态资源
    return env.ASSETS.fetch(request);
  },
};