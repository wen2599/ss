// frontend/public/_worker.js (适配后端路由版本)

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch';
      
      // 直接使用前端的路径作为后端的路由路径
      const newPath = url.pathname; // 例如: /api/get_results

      const newUrl = new URL(backendUrl + newPath + url.search);

      const newRequest = new Request(newUrl, request);

      const response = await fetch(newRequest);
      const newResponse = new Response(response.body, response);
      newResponse.headers.set('Access-Control-Allow-Origin', url.origin);
      // ... 其他 CORS 头 ...
      return newResponse;
    }

    return env.ASSETS.fetch(request);
  },
};
