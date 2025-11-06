export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    try {
      // ================= 规则 0: 终极防御 =================
      // 在所有逻辑之前，首先拦截对 /favicon.ico 的请求。
      // 如果文件不存在，我们就直接返回 404，不让这个请求
      // 进入下面任何可能导致 env.fetch() 崩溃的逻辑。
      if (pathname === '/favicon.ico') {
        // 直接返回一个明确的 "404 Not Found" 响应。
        // 这样浏览器就知道图标不存在，也不会显示错误。
        // 最重要的是，我们完全绕过了 env.fetch()。
        return new Response('Not Found', { status: 404 });
      }
      // ====================================================

      // 规则 1: 代理 API 请求
      if (pathname.startsWith('/api/')) {
        const backendUrl = 'https://wenge.cloudns.ch';
        const newUrl = new URL(backendUrl + pathname + url.search);
        const backendRequest = new Request(newUrl, request);
        return await fetch(backendRequest);
      }

      // 规则 2: 判断是否是静态资源 (排除 favicon.ico, 因为上面已经处理了)
      const isStaticAsset = /\.[^/]+$/.test(pathname);
      if (isStaticAsset) {
        return await env.fetch(request);
      }

      // 规则 3: 返回单页应用的 index.html
      const indexHtmlRequest = new Request(new URL('/index.html', url.origin), request);
      return await env.fetch(indexHtmlRequest);

    } catch (e) {
      console.error(`Worker Exception: ${e.message} for path: ${pathname}`);
      console.error(`Stack Trace: ${e.stack}`);
      return new Response('Internal Server Error (Final Defensive Version)', { status: 500 });
    }
  },
};
