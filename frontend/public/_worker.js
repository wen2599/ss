// frontend/public/_worker.js

export default {
  /**
   * 处理 HTTP 请求，主要用于代理前端对后端 API 的调用
   */
  async fetch(request, env) {
    const url = new URL(request.url);

    // 如果请求路径以 /api/ 开头，则将其代理到后端
    if (url.pathname.startsWith('/api/')) {
      
      // !! 重要：这是您后端服务器的真实地址 !!
      const BACKEND_URL = 'https://wenge.cloudns.ch';
      
      // !! 重要：这是您在后端.env文件中设置的密钥 !!
      const WORKER_SECRET = 'your_secret_key_shared_with_cf_worker';

      // 从原始请求路径中移除 /api/ 前缀
      const newPathname = url.pathname.substring(4);
      
      // 构建新的目标URL
      const targetUrl = new URL(newPathname + url.search, BACKEND_URL);

      // 创建一个新的请求对象，复制原始请求的所有信息
      const newRequest = new Request(targetUrl, request);

      // 添加自定义的安全密钥头，用于后端验证
      newRequest.headers.set('X-Worker-Secret', WORKER_SECRET);
      
      // 发送请求到后端并返回响应
      return fetch(newRequest);
    }

    // 如果请求不是 /api/，则作为静态资源请求，由 Pages 默认处理
    return env.ASSETS.fetch(request);
  },

  /**
   * 处理 Cloudflare Email Routing 转发来的邮件
   */
  async email(message, env, ctx) {
    // !! 重要：这是您后端服务器的真实地址 !!
    const BACKEND_URL = 'https://wenge.cloudns.ch';
      
    // !! 重要：这是您在后端.env文件中设置的密钥 !!
    const WORKER_SECRET = 'your_secret_key_shared_with_cf_worker';

    // 邮件接收地址
    const recipient = message.to; 
    // 发件人邮箱地址
    const sender = message.from; 
    
    // 将原始邮件内容 (ReadableStream) 读取为字符串
    const rawEmail = await new Response(message.raw).text();

    // 指向您后端处理邮件的端点
    const targetUrl = new URL('/proxy.php?action=receive_email', BACKEND_URL);

    try {
      // 将邮件数据 POST 到您的后端
      const response = await fetch(targetUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Worker-Secret': WORKER_SECRET, 
        },
        body: JSON.stringify({
          sender_email: sender,
          recipient_email: recipient, // 也将收件人地址发给后端，未来可能有用
          raw_email: rawEmail,
        }),
      });

      // 检查后端的响应
      if (response.ok) {
        // 如果后端成功处理 (状态码 200-299), 我们就认为邮件已妥善处理
        console.log(`来自 ${sender} 发往 ${recipient} 的邮件已成功转发并处理。`);
        // 不调用 setReject，邮件将被 Cloudflare 标记为成功接收
      } else {
        // 如果后端返回错误 (例如 500), 我们就拒绝该邮件，发件人会收到退信
        const errorText = await response.text();
        console.error(`后端处理邮件失败。状态码: ${response.status}. 响应: ${errorText}`);
        message.setReject(`无法投递到后端服务。服务器响应状态码 ${response.status}。`);
      }
    } catch (e) {
      // 如果连接后端的网络出错，我们也拒绝该邮件
      console.error(`转发邮件时网络错误: ${e.message}`);
      message.setReject(`无法连接到后端服务。`);
    }
  },
};
