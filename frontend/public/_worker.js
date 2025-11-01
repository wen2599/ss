export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendBase = 'https://wenge.cloudns.ch'; // 您的Serv00后端域名

    // 检查请求路径是否以 /api/ 开头
    if (url.pathname.startsWith('/api/')) {
      const newUrl = new URL(backendBase + url.pathname + url.search);

      // 处理预检请求 (OPTIONS)
      if (request.method === 'OPTIONS') {
        return handleOptions(request);
      }
      
      // --- FIX: Create a new Request object to properly forward the request body ---
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // 转发请求到后端
      let response = await fetch(newRequest);

      // 克隆响应，因为响应是不可变的
      response = new Response(response.body, response);

      // 添加CORS头部到所有响应
      addCORSHeaders(response);

      return response;
    }

    // 对于非/api/的请求，直接返回静态内容 (Cloudflare Pages会处理)
    return env.ASSETS.fetch(request);
  },
};

function addCORSHeaders(response) {
  response.headers.set('Access-Control-Allow-Origin', 'https://ss.wenxiuxiu.eu.org');
  response.headers.set('Access-control-allow-methods', 'GET, POST, PUT, DELETE, OPTIONS');
  response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  response.headers.set('Access-Control-Allow-Credentials', 'true');
  response.headers.set('Access-Control-Max-Age', '86400');
}

function handleOptions(request) {
  const response = new Response(null, { status: 204 });
  addCORSHeaders(response);
  return response;
}
