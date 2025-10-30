// 文件名: _worker.js
// 路径: frontend/public/_worker.js
// 目的: 作为 Cloudflare Pages 的边缘函数，代理所有 /api/* 的请求到真实后端。

export default {
  /**
   * fetch 函数是 Cloudflare Workers/Functions 的入口点。
   * 每个到达你网站的请求都会经过这里。
   * @param {Request} request - 进来的 HTTP 请求对象。
   * @param {object} env - 环境变量对象，env.ASSETS 用于获取静态资源。
   * @param {object} ctx - 执行上下文对象。
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 定义你的真实 PHP 后端服务器地址。
    const apiBaseUrl = "https://wenge.cloudns.ch";

    // 我们只处理路径以 /api/ 开头的请求。
    if (url.pathname.startsWith('/api/')) {
      
      // 1. 构造目标后端 URL
      // 从原始路径中移除 '/api' 前缀。 e.g., '/api/lottery/get_latest.php' -> '/lottery/get_latest.php'
      const newPath = url.pathname.substring(4); 
      // 将处理过的路径和原始查询参数，与后端基础 URL 结合。
      const destinationUrl = new URL(newPath + url.search, apiBaseUrl);

      // 2. 创建一个新的请求对象以转发
      // 我们需要复制原始请求的方法 (GET, POST, etc.), headers, 和 body.
      const newRequest = new Request(destinationUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow' // 告诉 fetch 自动处理重定向
      });

      try {
        // 3. 发送请求到真实后端
        console.log(`Proxying request: ${request.method} ${destinationUrl.toString()}`);
        const backendResponse = await fetch(newRequest);
        
        // 4. 处理后端响应并返回给浏览器
        // 我们需要创建一个新的 Response 对象，因为原始响应的 headers 是不可变的。
        // 这给了我们机会来修改 headers, 比如添加我们自己的 CORS 头。
        const responseHeaders = new Headers(backendResponse.headers);

        // 为本地开发和未来 APK 兼容性，我们设置宽松的 CORS 策略。
        // '*' 允许任何源访问此代理。
        responseHeaders.set('Access-Control-Allow-Origin', '*'); 
        responseHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        responseHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: responseHeaders
        });

      } catch (error) {
        // 如果连接后端失败，返回一个 502 Bad Gateway 错误。
        console.error(`Proxy failed for ${destinationUrl.toString()}:`, error);
        return new Response(`API proxy failed: ${error.message}`, { status: 502 });
      }
    }

    // 如果请求路径不是 /api/ 开头, 那么它就是一个普通的静态资源请求 (e.g., index.html, css, js).
    // `env.ASSETS.fetch(request)` 是 Cloudflare Pages 的标准做法，
    // 它会从你的部署文件中查找并返回对应的静态文件。
    return env.ASSETS.fetch(request);
  },
};