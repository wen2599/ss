// frontend/public/_worker.js
// 这个文件应该放在 frontend/public/ 目录下

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 拦截所有以 /api/ 开头的请求
    if (url.pathname.startsWith('/api/')) {
      const BACKEND_URL = 'https://wenge.cloudns.ch';
      const WORKER_SECRET = '816429fb-1649-4e48-9288-7629893311a6'; // 使用您提供的密钥

      // 构建目标URL
      // 移除 /api 前缀
      // 例如: /api/proxy.php?action=login -> /proxy.php?action=login
      const newPathname = url.pathname.substring(4);
      const targetUrl = new URL(newPathname + url.search, BACKEND_URL);

      // 创建一个到后端的新请求
      const newRequest = new Request(targetUrl, request);

      // 添加自定义的安全头
      newRequest.headers.set('X-Worker-Secret', WORKER_SECRET);
      
      // 发起请求并直接返回后端的响应
      // 重要的是，这里的fetch是在Worker（服务器端）执行的，不存在CORS问题
      return fetch(newRequest);
    }

    // 如果请求路径不匹配，则交由Cloudflare Pages处理静态资源
    return env.ASSETS.fetch(request);
  },
};