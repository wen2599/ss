// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 只代理 /api/ 开头的路径
    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch'; // 你的后端地址
      
      // 构造新的目标 URL
      // url.pathname 是 /api/get_results
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      // 创建一个新的请求，转发到后端
      const newRequest = new Request(newUrl, request);

      try {
        const response = await fetch(newRequest);

        // 创建一个可修改的响应副本
        const newResponse = new Response(response.body, response);

        // 设置 CORS 头部，允许你的前端域名访问
        newResponse.headers.set('Access-Control-Allow-Origin', url.origin); // url.origin 就是 https://ss.wenxiuxiu.eu.org
        newResponse.headers.set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type');
        
        return newResponse;

      } catch (e) {
        // 如果后端 fetch 失败，返回一个错误信息
        return new Response('Backend fetch failed: ' + e.message, { status: 502 });
      }
    }

    // 对于非 /api/ 请求，正常提供静态资源
    return env.ASSETS.fetch(request);
  },
};