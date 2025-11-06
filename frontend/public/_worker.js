// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 定义后端的API地址
    const backendUrl = "https://wenge.cloudns.ch/api.php";

    // 检查请求路径是否是我们想要代理的路径，例如 /api/ 或 /api/data
    // 这里我们简单地将所有对 /api 开头的请求都代理到后端的 api.php
    if (url.pathname.startsWith('/api')) {
      
      // 创建一个新的指向后端服务的请求
      // 我们只关心GET请求，如果需要支持POST等，需要复制更多请求属性
      const backendRequest = new Request(backendUrl, {
        method: 'GET', // 前端获取数据，用GET
        headers: request.headers, // 可以选择性地传递原始请求头
      });

      try {
        const backendResponse = await fetch(backendRequest);

        // 获取后端响应后，我们需要创建一个新的响应来返回给浏览器
        // 这是因为响应对象是不可变的
        const response = new Response(backendResponse.body, backendResponse);

        // --- 核心步骤：添加CORS头 ---
        // 允许来自你前端域名的请求
        response.headers.set('Access-Control-Allow-Origin', url.origin);
        // 如果需要，可以添加更多CORS头
        response.headers.set('Access-Control-Allow-Methods', 'GET, HEAD, POST, OPTIONS');
        response.headers.set('Access-Control-Allow-Headers', 'Content-Type');

        return response;

      } catch (error) {
        return new Response('Error connecting to backend API: ' + error.message, { status: 502 });
      }
    }

    // 对于非 /api 的请求 (例如主页HTML, CSS, JS文件)，让Cloudflare Pages的默认行为来处理
    // `env.ASSETS.fetch(request)` 会从你部署的文件中提供服务
    return env.ASSETS.fetch(request);
  },
};