export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    // 规则 1: 代理 API 请求 (保持不变)
    if (pathname.startsWith('/api/')) {
      try {
        const backendUrl = 'https://wenge.cloudns.ch';
        const newUrl = new URL(backendUrl + pathname + url.search);
        const backendRequest = new Request(newUrl, request);
        return await fetch(backendRequest);
      } catch (e) {
        return new Response(`API Proxy Error: ${e.message}`, { status: 502 });
      }
    }

    // 规则 2: 使用 env.ASSETS.fetch() 来服务所有非 API 请求。
    // env.ASSETS.fetch() 是 Pages Functions 中获取静态资源的官方、标准方式。
    // 它非常底层和稳定。
    try {
      // 检查请求的是否是页面导航 (没有文件后缀)
      const isPageNavigation = !/\.[^/]+$/.test(pathname);

      if (isPageNavigation) {
        // 如果是页面导航 (/, /login, etc.), 我们就明确地请求 /index.html。
        // 我们不再创建新的 Request 对象，而是直接把原始请求交给 ASSETS.fetch，
        // 它内部有一个机制，在找不到文件时会自动服务 /index.html（如果配置正确）。
        // 为了更保险，我们直接请求 index.html。
        // 注意：这里我们不能直接传 request，因为原始请求的URL是/，我们需要明确告诉它要/index.html
        const indexUrl = new URL(url);
        indexUrl.pathname = '/index.html';
        return await env.ASSETS.fetch(new Request(indexUrl));
        
      } else {
        // 如果是静态资源请求 (e.g., /assets/index.js),
        // 直接让 ASSETS.fetch 去处理原始请求。
        return await env.ASSETS.fetch(request);
      }
    } catch (e) {
      // 如果 ASSETS.fetch 也失败了，那问题就非常严重了。
      // 这通常意味着构建产物根本没有被正确上传。
      console.error(`ASSETS.fetch Exception: ${e.message} for path: ${pathname}`);
      // 为了调试，我们返回更详细的错误
      return new Response(`Static asset fetch failed for ${pathname}. Error: ${e.message}`, { status: 500 });
    }
  },
};
