export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // 1. 如果是 API 请求，直接代理到后端
    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch';
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      const backendRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow',
      });

      return fetch(backendRequest);
    }
    
    // 2. 对于其他所有请求，让 Cloudflare Pages 的原生静态资源服务处理
    // env.fetch 会处理 React 的路由和 public 目录下的静态文件
    return env.fetch(request);
  },
};
