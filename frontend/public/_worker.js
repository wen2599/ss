// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    // 解析原始请求的URL
    const url = new URL(request.url);

    // 如果请求路径以 /api/ 开头，则将其代理到后端
    if (url.pathname.startsWith('/api/')) {
      
      // !! 重要：这是您后端服务器的真实地址 !!
      const BACKEND_URL = 'https://wenge.cloudns.ch';
      
      // !! 重要：这是您在后端.env文件中设置的密钥 !!
      const WORKER_SECRET = 'your_secret_key_shared_with_cf_worker';

      // 从原始请求路径中移除 /api/ 前缀
      // 例如：/api/proxy.php?action=login -> /proxy.php?action=login
      const newPathname = url.pathname.substring(4); 
      
      // 构建新的目标URL
      const targetUrl = new URL(newPathname + url.search, BACKEND_URL);

      // 创建一个新的请求对象，复制原始请求的方法、头和主体
      const newRequest = new Request(targetUrl, request);

      // 添加我们自定义的安全密钥头
      newRequest.headers.set('X-Worker-Secret', WORKER_SECRET);
      
      // 为了调试，可以打印出目标URL
      console.log(`Forwarding request to: ${targetUrl}`);

      // 发送请求到后端并返回响应
      return fetch(newRequest);
    }

    // 如果请求不是 /api/，则让 Cloudflare Pages 正常处理 (返回静态文件)
    // env.ASSETS.fetch 是 Cloudflare Pages 提供的固定方法
    return env.ASSETS.fetch(request);
  },
};