// A simple pass-through worker.
// It intercepts requests to /api/* and forwards them to the PHP backend.
export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    
    // 我们只代理 /api/ 开头的请求
    if (url.pathname.startsWith('/api/')) {
      // 后端服务器的地址
      const backendUrl = 'https://wenge.cloudns.ch';
      
      // 创建一个新的 URL 指向后端
      const newUrl = new URL(backendUrl + url.pathname + url.search);
      
      // 创建一个新的请求，复制原始请求的方法、头部和主体，但使用新的 URL
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // 发送到后端并返回响应
      const response = await fetch(newRequest);
      
      // 创建一个新的响应头，以允许跨域（虽然现在是同源代理，但这是个好习惯）
      const headers = new Headers(response.headers);
      headers.set('Access-Control-Allow-Origin', url.origin);
      headers.set('Access-Control-Allow-Credentials', 'true');

      return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: headers
      });
    }

    // 对于非 /api/ 的请求，让 Cloudflare Pages 正常处理（返回静态文件）
    return env.fetch(request);
  }
};