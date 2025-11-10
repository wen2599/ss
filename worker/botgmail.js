// File: Cloudflare Worker (ssgamil) - 使用 Bearer Token 认证

/**
 * Base64 编码函数
 */
function utf8_to_b64(str) {
  try {
    return btoa(unescape(encodeURIComponent(str)));
  } catch (e) {
    // 备用编码方案
    try {
      const encoder = new TextEncoder();
      const uint8Array = encoder.encode(str);
      let binaryString = '';
      uint8Array.forEach((byte) => {
        binaryString += String.fromCharCode(byte);
      });
      return btoa(binaryString);
    } catch (error) {
      console.error("Base64 编码失败:", error);
      throw new Error("数据编码失败");
    }
  }
}

/**
 * 流转换为字符串
 */
async function streamToString(stream) {
  const reader = stream.getReader();
  const decoder = new TextDecoder();
  let result = '';
  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      result += decoder.decode(value, { stream: true });
    }
  } finally {
    reader.releaseLock();
  }
  return result;
}

export default {
  async fetch(request, env, ctx) {
    return new Response('邮件处理 Worker 运行中', {
      headers: { 'Content-Type': 'text/plain; charset=utf-8' }
    });
  },

  async email(message, env, ctx) {
    let requestSuccess = false;
    
    try {
      const EMAIL_TARGET_URL = env.EMAIL_TARGET_URL;
      const EMAIL_SECRET = env.EMAIL_WORKER_SECRET;

      // 环境变量检查
      if (!EMAIL_TARGET_URL || !EMAIL_SECRET) {
        console.error("Worker: 缺少必要的环境变量");
        message.setReject("服务器配置错误");
        return;
      }

      console.log(`Worker: 开始处理来自 ${message.from} 的邮件`);

      // 获取原始邮件内容
      const rawEmail = await streamToString(message.raw);
      const sender = message.from;

      // 构建数据包
      const dataToSend = {
        sender: sender,
        raw_content: rawEmail
      };

      const jsonString = JSON.stringify(dataToSend);

      console.log(`Worker: 准备发送 POST 请求到 ${EMAIL_TARGET_URL}`);
      console.log(`Worker: 数据大小: ${jsonString.length} 字符`);

      // 发送 POST 请求 - 使用 Bearer Token 认证
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 30000);

      try {
        const response = await fetch(EMAIL_TARGET_URL, { 
          method: 'POST',
          signal: controller.signal,
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${EMAIL_SECRET}`,
            'User-Agent': 'Cloudflare-Email-Worker/1.0'
          },
          body: jsonString
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
          const errorText = await response.text();
          console.error(`Worker: 后端请求失败。状态: ${response.status}, 响应: ${errorText}`);
          
          if (response.status === 403) {
            message.setReject('认证失败 (403)');
          } else {
            message.setReject(`服务器错误 (${response.status})`);
          }
        } else {
          const responseData = await response.text();
          console.log(`Worker: 邮件处理成功，响应: ${responseData}`);
          requestSuccess = true;
        }
      } catch (fetchError) {
        clearTimeout(timeoutId);
        if (fetchError.name === 'AbortError') {
          console.error('Worker: 请求超时');
          message.setReject('处理超时');
        } else {
          console.error('Worker: 请求失败:', fetchError.message);
          message.setReject('网络请求失败: ' + fetchError.message);
        }
      }
      
    } catch (error) {
      console.error("Worker: 邮件处理未捕获错误:", error.stack);
      if (!requestSuccess) {
        message.setReject('处理过程中发生错误: ' . error.message);
      }
    }
  }
};