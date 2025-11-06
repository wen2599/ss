export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    try {
      // 规则 1: 代理所有 /api/ 开头的请求到后端服务器
      if (pathname.startsWith('/api/')) {
        const backendUrl = 'https://wenge.cloudns.ch';
        const newUrl = new URL(backendUrl + pathname + url.search);
        const backendRequest = new Request(newUrl, request);
        return await fetch(backendRequest);
      }

      // 规则 2: 检查请求的是否是静态资源 (判断路径中是否包含文件后缀)
      // 正则表达式 /\.[^/]+$/ 用于匹配路径末尾的 ".文件名" 模式
      const isStaticAsset = /\.[^/]+$/.test(pathname);

      if (isStaticAsset) {
        // 如果是静态资源 (如 .js, .css, .svg), 
        // 则让 Cloudflare Pages 的原生服务来提供它。
        return await env.fetch(request);
      }

      // 规则 3: 如果不是 API 请求，也不是静态资源请求，
      // 那么它就是一个页面导航请求 (如 /, /login, /dashboard)。
      // 对于所有这类请求，我们都应该返回应用的入口 index.html。
      // React Router 会在客户端接管后续的路由。
      const indexHtmlRequest = new Request(new URL('/index.html', url.origin), request);
      return await env.fetch(indexHtmlRequest);

    } catch (e) {
      console.error(`Worker Exception: ${e.message}`);
      console.error(`Stack Trace: ${e.stack}`);
      // 为了确认是这个版本的worker在报错，我改了一下错误信息
      return new Response('Internal Server Error (SPA routing version)', { status: 500 });
    }
  },
};
