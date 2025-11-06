export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    // 检查是否是常见的静态文件后缀
    const isStaticAsset = /\.(css|js|svg|png|jpg|jpeg|gif|ico|woff|woff2)$/.test(pathname);

    // 如果是 API 请求，代理到后端
    if (pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch'; // 你的后端地址
      const newUrl = new URL(backendUrl + pathname + url.search);

      const backendRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow',
      });
      return fetch(backendRequest);
    }

    // 如果是静态文件请求，或者不是 API 请求，
    // 直接交给 Cloudflare Pages 的原生服务处理
    if (isStaticAsset || !pathname.startsWith('/api/')) {
      return env.fetch(request);
    }
    
    // 兜底，理论上不会执行到这里
    return new Response('Not Found', { status: 404 });
  },
};
