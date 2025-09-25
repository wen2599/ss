// worker/email_handler.js （修正版：确保 FormData 被 PHP 正确识别）

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

function decodeQuotedPrintable(input) {
  return input
    .replace(/=(?:\r\n|\n|\r)/g, '')
    .replace(/=([A-Fa-f0-9]{2})/g, (m, hex) => String.fromCharCode(parseInt(hex, 16)));
}

function b64toBlob(base64, mime) {
  const byteChars = atob(base64);
  const byteNumbers = new Array(byteChars.length);
  for (let i = 0; i < byteChars.length; i++) {
    byteNumbers[i] = byteChars.charCodeAt(i);
  }
  return new Blob([new Uint8Array(byteNumbers)], { type: mime });
}

function parseEmail(rawEmail, options = {}) {
  const maxAttachmentCount = options.maxAttachmentCount || 10;
  const maxAttachmentSize = options.maxAttachmentSize || 8 * 1024 * 1024;
  const allowedAttachmentTypes = options.allowedAttachmentTypes || [
    "image/", "application/pdf", "text/plain", "application/zip"
  ];
  const boundaryMatch = rawEmail.match(/boundary="([^"]+)"/i) || rawEmail.match(/boundary=([^\r\n;]+)/i);
  const boundary = boundaryMatch ? boundaryMatch[1] : null;
  let textContent = '';
  let htmlContent = '';
  const attachments = [];
  let attachmentCount = 0;

  if (boundary) {
    const parts = rawEmail.split(new RegExp(`--${boundary}(?:--)?`, 'g')).filter(Boolean);
    for (const part of parts) {
      if (/Content-Type:\s*text\/plain/i.test(part) && !/Content-Disposition:\s*attachment/i.test(part)) {
        if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
          const base64Match = part.match(/\r?\n\r?\n([^]*)/);
          if (base64Match) textContent += atob(base64Match[1].replace(/\r?\n/g, ''));
        } else if (/Content-Transfer-Encoding:\s*quoted-printable/i.test(part)) {
          const qpMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (qpMatch) textContent += decodeQuotedPrintable(qpMatch[1]);
        } else {
          const plainMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (plainMatch) textContent += plainMatch[1].trim();
        }
      }
      if (/Content-Type:\s*text\/html/i.test(part) && !/Content-Disposition:\s*attachment/i.test(part)) {
        let html = '';
        if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
          const base64Match = part.match(/\r?\n\r?\n([^]*)/);
          if (base64Match) html += atob(base64Match[1].replace(/\r?\n/g, ''));
        } else if (/Content-Transfer-Encoding:\s*quoted-printable/i.test(part)) {
          const qpMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (qpMatch) html += decodeQuotedPrintable(qpMatch[1]);
        } else {
          const htmlMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (htmlMatch) html += htmlMatch[1].trim();
        }
        htmlContent += html;
      }
      if (/Content-Disposition:\s*attachment/i.test(part)) {
        if (attachmentCount++ >= maxAttachmentCount) continue;
        let filename = 'unnamed';
        const filenameMatch = part.match(/filename="([^"]+)"/i) || part.match(/filename=([^\r\n;]+)/i);
        if (filenameMatch) filename = filenameMatch[1].replace(/\s/g, '_');
        const contentTypeMatch = part.match(/Content-Type:\s*([^\r\n;]+)/i);
        const contentType = contentTypeMatch ? contentTypeMatch[1].trim() : 'application/octet-stream';
        if (!allowedAttachmentTypes.some(t => contentType.startsWith(t))) continue;
        let content = '';
        if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
          const base64Match = part.match(/\r?\n\r?\n([^]*)/);
          if (base64Match) content = base64Match[1].replace(/\r?\n/g, '');
        } else {
          const plainMatch = part.match(/\r?\n\r?\n([^]*)/);
          if (plainMatch) content = plainMatch[1];
        }
        try {
          let blob = /base64/i.test(part)
            ? b64toBlob(content, contentType)
            : new Blob([content], { type: contentType });
          if (blob.size > maxAttachmentSize) continue;
          attachments.push({ filename, blob, contentType });
        } catch {}
      }
    }
  } else {
    const textPart = rawEmail.match(/Content-Type:\s*text\/plain[^]*?\r?\n\r?\n([^]*)/i);
    if (textPart && textPart[1]) textContent = textPart[1].trim();
    const htmlPart = rawEmail.match(/Content-Type:\s*text\/html[^]*?\r?\n\r?\n([^]*)/i);
    if (htmlPart && htmlPart[1]) htmlContent = htmlPart[1].trim();
  }

  return { textContent, htmlContent, attachments };
}

export default {
  async email(message, env, ctx) {
    const PUBLIC_API_ENDPOINT = "https://ss.wenxiuxiu.eu.org";
    const WORKER_SECRET = "816429fb-1649-4e48-9288-7629893311a6";
    const MAX_BODY_LENGTH = 32 * 1024;
    const MAX_ATTACHMENT_COUNT = 10;
    const MAX_ATTACHMENT_SIZE = 8 * 1024 * 1024;
    const ALLOWED_ATTACHMENT_TYPES = [
      "image/", "application/pdf", "text/plain", "application/zip"
    ];

    const senderEmail =
      message.from ||
      (message.headers && message.headers.get && message.headers.get("from")) ||
      "";
    if (!senderEmail) {
      console.error("收到的邮件没有发件人地址，终止处理。");
      return;
    }

    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/is_user_registered?worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);
      if (!verificationResponse.ok) return;
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) return;
    } catch (error) { return; }

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
    } catch (err) {}

    if (chatContent.length > MAX_BODY_LENGTH) {
      chatContent = chatContent.slice(0, MAX_BODY_LENGTH) + "\n\n[内容过长，已被截断]";
    }
    if (htmlContent.length > MAX_BODY_LENGTH) {
      htmlContent = htmlContent.slice(0, MAX_BODY_LENGTH) + "\n\n[内容过长，已被截断]";
    }

    let messageId = "";
    try {
      if (message.headers && message.headers.get) {
        messageId = message.headers.get("message-id") || "";
      }
    } catch {}
    const safeEmail = senderEmail.replace(/[^a-zA-Z0-9_.-]/g, "_");
    const filename =
      `email-${safeEmail}-${Date.now()}${messageId ? "-" + messageId : ""}.txt`;

    // 确保字段顺序和名称
    const formData = new FormData();
    formData.append("worker_secret", WORKER_SECRET);
    formData.append("user_email", senderEmail);
    formData.append("raw_email_file", new Blob([chatContent], { type: "text/plain" }), filename);
    if (htmlContent) {
      formData.append("html_body", new Blob([htmlContent], { type: "text/html" }), filename.replace(".txt", ".html"));
    }
    for (const att of attachments) {
      formData.append("attachment", att.blob, att.filename);
    }

    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;
      // 不要设置 Content-Type，FormData 会自动设置（否则 PHP 接收不到 $_FILES）
      await fetch(uploadUrl, {
        method: "POST",
        body: formData,
      });
    } catch (error) {}
  },
};
