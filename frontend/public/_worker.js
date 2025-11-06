export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // ================== 安全网 (Safety Net) ==================
    // 我们用 try...catch 把所有逻辑都包起来。
    // 这样，无论内部的 fetch 或代理逻辑是否出错，
    // 我们都能捕获到异常，并返回一个正常的错误响应，
    // 而不是让整个 Worker 崩溃导致 Error 1101。
    // =========================================================
    try {
      // 规则 1: 如果是 API 请求，就代理到后端
      if (url.pathname.startsWith('/api/')) {
        const backendUrl = 'https://wenge.cloudns.ch';
        const newUrl = new URL(backendUrl + url.pathname + url.search);
        
        // 创建一个全新的请求对象，并转发
        const backendRequest = new Request(newUrl, request);
        
        return await fetch(backendRequest);
      }

      // 规则 2: 对于所有其他请求 (例如 /, /dashboard, /assets/...),
      // 交给 Cloudflare Pages 的原生静态资源服务处理。
      // env.fetch() 会负责返回你的 React 应用 (index.html) 或其资源 (js, css)。
      // 之前就是这一步在请求不存在的 favicon.ico 时可能导致了崩溃。
      return await env.fetch(request);

    } catch (e) {
      // 捕获到异常！
      // 这意味着上面的代码块中发生了严重错误。
      // 我们不再让它崩溃，而是：
      
      // 1. 在后台记录详细的错误信息，方便你以后在 Cloudflare Dashboard 查看日志
      console.error(`Worker Exception: ${e.message}`);
      console.error(`Stack Trace: ${e.stack}`);

      // 2. 向用户返回一个通用的、无害的 500 错误页面。
      // 这远比看到 Cloudflare 的 1101 错误页面要好。
      return new Response('服务器内部发生错误，但我们已捕获到它。'， { status: 500 });
    }
  },
};
