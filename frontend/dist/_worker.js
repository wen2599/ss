// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const frontendOrigin = new URL(request.url).origin;
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      if (request.method === 'OPTIONS') {
        return new Response(null, {
          headers: {
            'Access-Control-Allow-Origin': frontendOrigin,
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
            'Access-Control-Allow-Credentials': 'true',
            'Access-Control-Max-Age': '86400',
          },
        });
      }

      const backendUrl = new URL(request.url);
      backendUrl.hostname = 'wenge.cloudns.ch';
      backendUrl.protocol = 'https';

      const backendHeaders = new Headers(request.headers);
      backendHeaders.set('Host', backendUrl.hostname);
      backendHeaders.set('Origin', frontendOrigin);

      const backendRequest = new Request(backendUrl.toString(), {
          method: request.method,
          headers: backendHeaders,
          body: request.body,
          redirect: 'follow'
      });

      let backendResponse;
      try {
        // 将请求转发到后端。
        backendResponse = await fetch(backendRequest);
      } catch (error) {
        // 如果后端 fetch 失败 (例如，网络错误、DNS 错误)，则返回 502 Bad Gateway。
        console.error(`Backend fetch failed: ${error.message}`);
        return new Response('Bad Gateway', { status: 502, statusText: 'Bad Gateway' });
      }

      // 创建一个响应的可变副本，以添加我们自己的头。
      const response = new Response(backendResponse.body, backendResponse);

      // 在返回给浏览器的响应上设置/覆盖 CORS 头。
      response.headers.set('Access-Control-Allow-Origin', frontendOrigin);
      response.headers.set('Access-Control-Allow-Credentials', 'true');

      // 附加 Vary: Origin 是一个好习惯。
      response.headers.append('Vary', 'Origin');

      return response;
    }

    // 否则，提供静态资源。
    return env.ASSETS.fetch(request);
  },
};
