// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    // 确保这里的后端URL是正确的
    const backendUrl = "https://wenge.cloudns.ch/api.php";

    // 我们只代理 /api 开头的请求
    if (url.pathname.startsWith('/api')) {
      try {
        // 创建一个新的请求，以防原始请求中有不兼容的头部
        const backendRequest = new Request(backendUrl, {
            method: "GET",
            headers: {
                "User-Agent": "Cloudflare-Worker" // 添加一个User-Agent，有时有助于调试
            }
        });
        
        const backendResponse = await fetch(backendRequest);

        // 检查后端响应是否成功 (HTTP 状态码 200-299)
        if (!backendResponse.ok) {
          const errorText = await backendResponse.text();
          const status = backendResponse.status;
          const statusText = backendResponse.statusText;
          
          return new Response(
            JSON.stringify({
              success: false,
              message: `Backend returned an error.`,
              error_details: {
                  status: status,
                  statusText: statusText,
                  responseText: errorText
              }
            }), {
              status: 502, // Bad Gateway
              headers: {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*', // 允许所有来源
              },
            }
          );
        }

        // 后端响应成功，我们添加CORS头并返回
        const response = new Response(backendResponse.body, backendResponse);
        response.headers.set('Access-Control-Allow-Origin', '*'); // 使用通配符，更简单
        response.headers.set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        response.headers.set('Content-Type', 'application/json'); // 确保内容类型是JSON
        
        return response;

      } catch (error) {
        // 如果 fetch 本身抛出异常 (例如DNS解析失败, 网络不通)
        return new Response(
            JSON.stringify({
              success: false,
              message: 'Cloudflare Worker failed to fetch the backend API.',
              error_details: {
                  name: error.name,
                  message: error.message,
                  cause: error.cause ? error.cause.toString() : 'N/A' // **这个 cause 属性非常重要**
              }
            }), {
              status: 502,
              headers: {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*',
              },
            }
          );
      }
    }

    // 对于非 /api 的请求，交由 Cloudflare Pages 处理静态资源
    return env.ASSETS.fetch(request);
  },
};