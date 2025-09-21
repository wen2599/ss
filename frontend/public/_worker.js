// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    // 1. 获取原始请求的 URL
    const url = new URL(request.url);

    // 2. 我们只代理对 /api/ 路径的请求
    if (url.pathname.startsWith('/api/')) {
      // 3. 定义你的后端 API 地址
      const backendUrl = 'https://wenge.cloudns.ch';

      // 4. 创建一个新的 URL 指向后端
      // 例如：将 https://ss.wenxiuxiu.eu.org/api/process.php
      // 转换为：https://wenge.cloudns.ch/api/process.php
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      // 5. 创建一个新的请求对象，复制原始请求的方法、头部和主体
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // 6. 向后端服务器发起请求
      console.log(`正在代理请求至: ${newUrl.toString()}`);
      
      const response = await fetch(newRequest);

      // 7. 将后端的响应直接返回给浏览器
      // 创建一个新的响应副本，因为响应体只能被读取一次
      return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: response.headers
      });
    }

    // 8. 如果请求的不是 /api/ 路径，则继续提供 Pages 的静态资源（你的 React 应用）
    // 'ASSETS' 是 Cloudflare Pages 提供的特殊对象，代表你的静态站点
    return env.ASSETS.fetch(request);
  },
};
