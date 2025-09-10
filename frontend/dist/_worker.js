/**
 * Cloudflare Worker for API Proxy (Enhanced Version)
 *
 * 增强特性：
 * - 支持 OPTIONS 预检请求自动 CORS 响应
 * - 支持自定义响应头（含 CORS 头）
 * - 支持 POST/PUT/DELETE 等所有方法和 body
 * - 自动转发原始 headers，排除部分敏感头
 * - 更详细的错误处理和日志
 * - 支持 /api/ 代理 + 其它路径自动交给 Pages 处理
 */
export default {
  async fetch(request, env, ctx) {
    const backendHost = 'https://9522.ip-ddns.com';
    const url = new URL(request.url);

    // --------- 1. 处理 API 代理 ---------
    if (url.pathname.startsWith('/api/')) {
      // 1.1. 处理 CORS 预检（OPTIONS）请求
      if (request.method === 'OPTIONS') {
        return new Response(null, {
          status: 204,
          headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age': '86400',
          },
        });
      }

      // 1.2. 转发 API 请求
      const backendUrl = `${backendHost}${url.pathname}${url.search}`;

      // 拷贝 headers，剔除 Host、CF-*、Sec-* 等敏感头
      const newHeaders = new Headers();
      for (const [k, v] of request.headers.entries()) {
        if (
          !/^host$/i.test(k) &&
          !/^cf-/i.test(k) &&
          !/^sec-/i.test(k)
        ) {
          newHeaders.set(k, v);
        }
      }

      // 保证 Host 头为后端域名（部分服务端需要）
      newHeaders.set('Host', new URL(backendHost).hostname);

      const init = {
        method: request.method,
        headers: newHeaders,
        redirect: 'follow',
        duplex: 'half', // Required for streaming request body
      };

      // 仅 GET/HEAD 没有 body，其它带 body
      if (request.method !== 'GET' && request.method !== 'HEAD') {
        init.body = request.body;
      }

      let backendResp;
      try {
        backendResp = await fetch(backendUrl, init);
      } catch (error) {
        console.error('Error proxying to backend:', error.message);
        return new Response('API backend unavailable.', { status: 502 });
      }

      // 复制响应体和头
      const respHeaders = new Headers(backendResp.headers);

      // 强制写入CORS头，防止浏览器报错
      respHeaders.set('Access-Control-Allow-Origin', '*');
      respHeaders.set('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
      respHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
      respHeaders.set('Access-Control-Expose-Headers', 'Content-Type, Authorization');

      // 可选：移除后端返回的CORS头，防止冲突
      respHeaders.delete('Access-Control-Allow-Credentials');
      respHeaders.delete('Access-Control-Max-Age');

      return new Response(backendResp.body, {
        status: backendResp.status,
        statusText: backendResp.statusText,
        headers: respHeaders,
      });
    }

    // --------- 2. 其它静态资源 ---------
    // 交给 Cloudflare Pages 处理
    return env.ASSETS.fetch(request);
  }
};
