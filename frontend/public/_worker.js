// --- Worker 入口点 ---
export default {
  /**
   * 处理进入 Worker 的请求。
   * - API 请求 (/check_session, /login etc.) 会被转发到后端 PHP 服务器。
   * - 其他所有请求 (e.g., /, /index.html, .css, .js) 会被视为静态资源请求，由 Cloudflare Pages 提供服务。
   */
  // eslint-disable-next-line no-unused-vars
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // 定义需要转发到后端的 API 路径列表
    const apiRoutes = [
      '/check_session',
      '/login',
      '/register', // <-- 确保 /register 路由被包含
      '/get_numbers',
      '/is_user_registered',
      '/email_upload',
      '/get_lottery_data',
      '/save_lottery_data',
      '/delete_lottery_data',
      '/get_user_by_email',
      '/get_system_status',
      '/tg_webhook'
    ];

    const isApiRequest = url.pathname.startsWith('/api/') || apiRoutes.some(route => url.pathname.startsWith(route));

    if (isApiRequest) {
      const backendServer = "https://wenge.cloudns.ch";
      
      // 从路径中提取端点 (e.g., /check_session -> check_session)
      const endpoint = url.pathname.substring(1);

      // 为后端请求创建一个新的 URL
      const backendUrl = new URL(backendServer + "/index.php");

      // 为后端路由设置 'endpoint' 查询参数
      backendUrl.searchParams.set('endpoint', endpoint);

      // 将原始请求中的任何其他查询参数附加到后端 URL
      for (const [key, value] of url.searchParams.entries()) {
        if (key !== 'endpoint') { // 避免重复添加 endpoint
          backendUrl.searchParams.append(key, value);
        }
      }

      // 使用正确构建的后端 URL 转发请求
      return fetch(new Request(backendUrl, request));
    }
    
    // 对于非 API 请求，从 Pages 静态资源中提供服务
    return env.ASSETS.fetch(request);
  },

  /**
   * email() 函数保持不变...
   */
  // eslint-disable-next-line no-unused-vars
  async email(message, env, ctx) {
    // ... (原始的 email 处理代码保持不变)
  },
};