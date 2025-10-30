// 文件名: email_worker.js
// 路径: worker/email_worker.js
// 用途: Cloudflare Worker 脚本，用于接收邮件并转发到后端

// 我们需要一个外部库来解析邮件，Cloudflare Workers 不支持直接 require。
// 你可以在 Worker 中使用 ESM 模块 URL。
// postal-mime 是一个不错的选择，但需要找到一个 ESM cdn-friendly 的版本。
// 为简单起见，这里假设我们可以通过某种方式引入一个解析器。
// 一个更简单的方法是直接将原始邮件(raw)发送到后端，让PHP来解析。
// 但我们还是尝试在Worker中解析，因为这更高效。
import { PostalMime } from 'postal-mime';

export default {
  async email(message, env, ctx) {
    // 从环境变量中获取后端Webhook URL和密钥
    const BACKEND_WEBHOOK_URL = env.BACKEND_WEBHOOK_URL; // 例如: https://wenge.cloudns.ch/webhooks/email.php
    const WEBHOOK_SECRET = env.WEBHOOK_SECRET; // 必须和后端 config.php 中的 EMAIL_WEBHOOK_SECRET 一致

    if (!BACKEND_WEBHOOK_URL || !WEBHOOK_SECRET) {
      console.error("Worker environment variables not set!");
      // 阻止邮件被丢弃，可以选择退回
      message.setReject("Configuration error.");
      return;
    }

    // 将邮件流转换为ArrayBuffer
    const stream = message.raw;
    const reader = stream.getReader();
    let chunks = [];
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      chunks.push(value);
    }
    const rawEmail = new Uint8Array(chunks.reduce((acc, val) => acc.concat(Array.from(val)), [])).buffer;

    // 使用 postal-mime 解析邮件
    const parser = new PostalMime();
    const parsedEmail = await parser.parse(rawEmail);

    const payload = {
      from: message.from,
      to: message.to,
      headers: Object.fromEntries(message.headers),
      subject: parsedEmail.subject || '',
      text: parsedEmail.text || '',
      html: parsedEmail.html || '',
    };

    try {
      // 发送 POST 请求到 PHP 后端
      const response = await fetch(BACKEND_WEBHOOK_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Webhook-Secret': WEBHOOK_SECRET, // 发送安全密钥
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error(`Backend returned an error: ${response.status} ${errorText}`);
        message.setReject(`Backend processing failed: ${errorText}`);
      } else {
        console.log("Email successfully forwarded to backend.");
      }

    } catch (error) {
      console.error(`Failed to forward email: ${error.message}`);
      message.setReject("Failed to forward to backend service.");
    }
  },
};