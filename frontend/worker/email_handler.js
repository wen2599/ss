// worker/email_handler.js （全面增强版，支持附件、HTML正文、类型过滤、大小限制、详细日志、灵活配置）

// ========== 辅助函数区域 ==========

// 将 ReadableStream 转为字符串
async function streamToString(stream) {
  const reader = stream.getReader();
  const decoder = new TextDecoder();
  let result = '';
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    result += decoder.decode(value, { stream: true });
  }
  return result;
}

// quoted-printable 解码
function decodeQuotedPrintable(input) {
  return input
    .replace(/=(?:\r\n|\n|\r)/g, '') // 软换行
    .replace(/=([A-Fa-f0-9]{2})/g, (m, hex) => String.fromCharCode(parseInt(hex, 16)));
}

// base64 转 Blob 辅助
function b64toBlob(base64, mime) {
  const byteChars = atob(base64);
  const byteNumbers = new Array(byteChars.length);
  for (let i = 0; i < byteChars.length; i++) {
    byteNumbers[i] = byteChars.charCodeAt(i);
  }
  const byteArray = new Uint8Array(byteNumbers);
  return new Blob([byteArray], { type: mime });
}

// 解析邮件MIME内容，提取正文及附件（支持text/plain、text/html、附件，类型、大小限制）
function parseEmail(rawEmail, options = {}) {
  // 配置项
  const maxAttachmentCount = options.maxAttachmentCount || 10;
  const maxAttachmentSize = options.maxAttachmentSize || 8 * 1024 * 1024; // 单附件最大8MB
  const allowedAttachmentTypes = options.allowedAttachmentTypes || [
    "image/", "application/pdf", "text/plain", "application/zip"
  ];

  // boundary
  const boundaryMatch = rawEmail.match(/boundary="([^"]+)"/i) || rawEmail.match(/boundary=([^\r\n;]+)/i);
  const boundary = boundaryMatch ? boundaryMatch[1] : null;
  let textContent = '';
  let htmlContent = '';
  const attachments = [];
  let attachmentCount = 0;

  if (boundary) {
    // 分割所有部分
    const parts = rawEmail.split(new RegExp(`--${boundary}(?:--)?`, 'g')).filter(Boolean);
    for (const part of parts) {
      // ---- 正文处理 ----
      if (/Content-Type:\s*text\/plain/i.test(part) && !/Content-Disposition:\s*attachment/i.test(part)) {
        // 编码处理
        if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
          const base64Match = part.match(/\r?\n\r?\n([^]*)/);
          if (base64Match) {
            try {
              textContent += atob(base64Match[1].replace(/\r?\n/g, ''));
            } catch {}
          }
        } else if (/Content-Transfer-Encoding:\s*quoted-printable/i.test(part)) {
          const qpMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (qpMatch) textContent += decodeQuotedPrintable(qpMatch[1]);
        } else {
          const plainMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (plainMatch) textContent += plainMatch[1].trim();
        }
      }
      // 如果是HTML内容，优先收集
      if (/Content-Type:\s*text\/html/i.test(part) && !/Content-Disposition:\s*attachment/i.test(part)) {
        let html = '';
        if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
          const base64Match = part.match(/\r?\n\r?\n([^]*)/);
          if (base64Match) {
            try {
              html += atob(base64Match[1].replace(/\r?\n/g, ''));
            } catch {}
          }
        } else if (/Content-Transfer-Encoding:\s*quoted-printable/i.test(part)) {
          const qpMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (qpMatch) html += decodeQuotedPrintable(qpMatch[1]);
        } else {
          const htmlMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (htmlMatch) html += htmlMatch[1].trim();
        }
        htmlContent += html;
      }
      // ---- 附件处理 ----
      if (/Content-Disposition:\s*attachment/i.test(part)) {
        if (attachmentCount++ >= maxAttachmentCount) continue; // 附件数量限制
        // 获取附件名
        let filename = 'unnamed';
        const filenameMatch = part.match(/filename="([^"]+)"/i) || part.match(/filename=([^\r\n;]+)/i);
        if (filenameMatch) filename = filenameMatch[1].replace(/\s/g, '_');
        // 获取内容类型
        const contentTypeMatch = part.match(/Content-Type:\s*([^\r\n;]+)/i);
        const contentType = contentTypeMatch ? contentTypeMatch[1].trim() : 'application/octet-stream';
        // 附件类型过滤
        if (!allowedAttachmentTypes.some(t => contentType.startsWith(t))) continue;
        // 获取内容
        let content = '';
        if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
          const base64Match = part.match(/\r?\n\r?\n([^]*)/);
          if (base64Match) content = base64Match[1].replace(/\r?\n/g, '');
        } else {
          const plainMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (plainMatch) content = plainMatch[1];
        }
        // 转为Blob，大小限制
        try {
          let blob = /base64/i.test(part)
            ? b64toBlob(content, contentType)
            : new Blob([content], { type: contentType });
          if (blob.size > maxAttachmentSize) continue; // 单个附件过大跳过
          attachments.push({ filename, blob, contentType });
        } catch {}
      }
    }
  } else {
    // 非MIME分隔，直接寻找纯文本
    const textPart = rawEmail.match(/Content-Type:\s*text\/plain[^]*?\r?\n\r?\n([^]*)/i);
    if (textPart && textPart[1]) textContent = textPart[1].trim();
    // HTML正文
    const htmlPart = rawEmail.match(/Content-Type:\s*text\/html[^]*?\r?\n\r?\n([^]*)/i);
    if (htmlPart && htmlPart[1]) htmlContent = htmlPart[1].trim();
  }

  return { textContent, htmlContent, attachments };
}

// ========== 核心处理逻辑 ==========

export default {
  async email(message, env, ctx) {
    // ========== 配置区 ==========
    const PUBLIC_API_ENDPOINT = "https://ss.wenxiuxiu.eu.org";
    const WORKER_SECRET = "816429fb-1649-4e48-9288-7629893311a6";
    const MAX_BODY_LENGTH = 32 * 1024; // 正文最大32KB
    const MAX_ATTACHMENT_COUNT = 10;   // 附件最大数量
    const MAX_ATTACHMENT_SIZE = 8 * 1024 * 1024; // 单附件8MB
    // 允许的附件类型
    const ALLOWED_ATTACHMENT_TYPES = [
      "image/", "application/pdf", "text/plain", "application/zip"
    ];

    // ========== 1. 获取并校验发件人 ==========
    const senderEmail =
      message.from ||
      (message.headers && message.headers.get && message.headers.get("from")) ||
      "";
    if (!senderEmail) {
      console.error("收到的邮件没有发件人地址，终止处理。");
      return;
    }

    // ========== 2. 用户注册校验 ==========
    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/is_user_registered?worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);

      if (!verificationResponse.ok) {
        console.error(`用户校验请求失败，状态码：${verificationResponse.status}。`);
        return;
      }
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) {
        console.log(`后端拒绝了来自未注册用户 '${senderEmail}' 的邮件。`);
        return;
      }
    } catch (error) {
      console.error("校验用户邮箱失败，错误信息：" + error.message);
      return;
    }

    // ========== 3. 解析邮件正文和附件 ==========
    let chatContent = "邮件没有包含可识别的纯文本内容。";
    let htmlContent = "";
    let attachments = [];
    try {
      const rawEmail = await streamToString(message.raw);
      const parsed = parseEmail(rawEmail, {
        maxAttachmentCount: MAX_ATTACHMENT_COUNT,
        maxAttachmentSize: MAX_ATTACHMENT_SIZE,
        allowedAttachmentTypes: ALLOWED_ATTACHMENT_TYPES
      });
      if (parsed.textContent) chatContent = parsed.textContent;
      if (parsed.htmlContent) htmlContent = parsed.htmlContent;
      attachments = parsed.attachments || [];
    } catch (err) {
      console.error("解析邮件内容失败：" + err.message);
    }

    // ========== 4. 正文长度限制 ==========
    if (chatContent.length > MAX_BODY_LENGTH) {
      chatContent = chatContent.slice(0, MAX_BODY_LENGTH) + "\n\n[内容过长，已被截断]";
    }
    if (htmlContent.length > MAX_BODY_LENGTH) {
      htmlContent = htmlContent.slice(0, MAX_BODY_LENGTH) + "\n\n[内容过长，已被截断]";
    }

    // ========== 5. 构造表单数据 ==========
    let messageId = "";
    try {
      if (message.headers && message.headers.get) {
        messageId = message.headers.get("message-id") || "";
      }
    } catch {}
    const safeEmail = senderEmail.replace(/[^a-zA-Z0-9_.-]/g, "_");
    const filename =
      `email-${safeEmail}-${Date.now()}${messageId ? "-" + messageId : ""}.txt`;

    const formData = new FormData();
    formData.append("chat_file", new Blob([chatContent], { type: "text/plain" }), filename);
    if (htmlContent) {
      formData.append("html_body", new Blob([htmlContent], { type: "text/html" }), filename.replace(".txt", ".html"));
    }
    formData.append("worker_secret", WORKER_SECRET);
    formData.append("user_email", senderEmail);

    // ========== 6. 添加附件 ==========
    for (const att of attachments) {
      // 附件字段名格式：attachment，filename自动传给后端
      formData.append("attachment", att.blob, att.filename);
    }

    // ========== 7. 上传 ==========
    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;
      const uploadResponse = await fetch(uploadUrl, {
        method: "POST",
        body: formData,
      });
      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error(`后端上传错误：${uploadResponse.status} ${uploadResponse.statusText}`, errorText);
      } else {
        console.log(`成功上传了 ${senderEmail} 的邮件内容，正文、HTML正文、附件数量：${attachments.length}。`);
      }
    } catch (error) {
      console.error("上传 API 请求失败：" + error.message);
    }
  },
};
