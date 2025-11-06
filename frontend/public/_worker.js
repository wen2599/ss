export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // ================== 安全网 (Safety Net) ==================
    // 用 try...catch 包裹所有逻辑，防止任何意外导致 Worker 崩溃。
    // =========================================================
    try {
      // 规则 1: 如果是 API 请求，代理到后端
      if (url.pathname.startsWith('/api/')) {
        const backendUrl = 'https://wenge.cloudns.ch';
        const newUrl = new URL(backendUrl + url.pathname + url.search);
        
        // 创建新请求并转发
        const backendRequest = new Request(newUrl, request);
        
        return await fetch(backendRequest);
      }

      // 规则 2: 对于所有其他请求，交由 Cloudflare Pages 原生服务处理
      return await env.fetch(request);

    } catch (e) {
      // 捕获到异常
      console.error(`Worker Exception: ${e.message}`);
      console.error(`Stack Trace: ${e.stack}`);

      // 向用户返回一个通用的 500 错误响应
      // 这里的逗号必须是半角英文逗号 ","
      return new Response('An internal server error occurred.', { status: 500 });
    }
  },
};
