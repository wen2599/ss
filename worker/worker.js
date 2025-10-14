// worker/worker.js (Complete version combining proxy and new email handler)

// ========== 1. Helper Functions for Email Parsing ==========

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

// 解析邮件MIME内容，提取正文及附件
function parseEmail(rawEmail, options = {}) {
  const maxAttachmentCount = options.maxAttachmentCount || 10;
  const maxAttachmentSize = options.maxAttachmentSize || 8 * 1024 * 1024;
  const allowedAttachmentTypes = options.allowedAttachmentTypes || ["image/", "application/pdf", "text/plain", "application/zip"];
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
          if (base64Match) try { textContent += atob(base64Match[1].replace(/\r?\n/g, '')); } catch {}
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
          if (base64Match) try { html += atob(base64Match[1].replace(/\r?\n/g, '')); } catch {}
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
          let blob = /base64/i.test(part) ? b64toBlob(content, contentType) : new Blob([content], { type: contentType });
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

// ========== 2. Core Worker Logic ==========

export default {
  /**
   * Handles HTTP requests, acting as a proxy for the backend API.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://ss.wenxiuxiu.eu.org";

    // Proxy API calls (e.g., /login, /register) to the backend
    if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api/') || url.pathname === '/email_upload' || url.pathname === '/is_user_registered') {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      // Forward the request as-is to the backend.
      const backendRequest = new Request(backendUrl, request);
      return fetch(backendRequest);
    }

    // For other requests, it is assumed to be a static asset.
    // This requires a static site (like Cloudflare Pages) to be linked.
    if (env.ASSETS && typeof env.ASSETS.fetch === 'function') {
        return env.ASSETS.fetch(request);
    }
    
    // Fallback if no static assets are configured
    return new Response('Not found.', { status: 404 });
  },

  /**
   * Handles incoming emails.
   */
  async email(message, env, ctx) {
    // ========== Configuration from Environment Variables ==========
    const { PUBLIC_API_ENDPOINT, EMAIL_HANDLER_SECRET } = env;

    // Critical check for environment variables
    if (!PUBLIC_API_ENDPOINT || !EMAIL_HANDLER_SECRET) {
      console.error("Worker Email Handler: Missing required environment variables (PUBLIC_API_ENDPOINT or EMAIL_HANDLER_SECRET).");
      return;
    }
    
    console.log("Worker Email Handler Debug: EMAIL_HANDLER_SECRET from env is: [" + (EMAIL_HANDLER_SECRET ? "SET" : "EMPTY") + "]");

    const MAX_BODY_LENGTH = 32 * 1024;
    const MAX_ATTACHMENT_COUNT = 10;
    const MAX_ATTACHMENT_SIZE = 8 * 1024 * 1024;
    const ALLOWED_ATTACHMENT_TYPES = ["image/", "application/pdf", "text/plain", "application/zip"];

    const senderEmail = message.from || "";
    if (!senderEmail) {
      console.error("Worker Email Handler: Received email without a sender address. Discarding.");
      return;
    }

    // User registration check
    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/is_user_registered?worker_secret=${EMAIL_HANDLER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);
      if (!verificationResponse.ok) {
        console.error(`Worker Email Handler: User verification request failed with status: ${verificationResponse.status}.`);
        return;
      }
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) {
        console.log(`Worker Email Handler: Backend rejected email from unregistered user '${senderEmail}'.`);
        return;
      }
    } catch (error) {
      console.error("Worker Email Handler: User verification failed. Error: " + error.message);
      return;
    }

    // Parse email
    let chatContent = "Email did not contain recognizable plain text content.";
    let htmlContent = "";
    let attachments = [];
    try {
      const rawEmail = await streamToString(message.raw);
      const parsed = parseEmail(rawEmail, { maxAttachmentCount: MAX_ATTACHMENT_COUNT, maxAttachmentSize: MAX_ATTACHMENT_SIZE, allowedAttachmentTypes: ALLOWED_ATTACHMENT_TYPES });
      if (parsed.textContent) chatContent = parsed.textContent;
      if (parsed.htmlContent) htmlContent = parsed.htmlContent;
      attachments = parsed.attachments || [];
    } catch (err) {
      console.error("Worker Email Handler: Failed to parse email content. Error: " + err.message);
    }

    // Truncate body if too long
    if (chatContent.length > MAX_BODY_LENGTH) chatContent = chatContent.slice(0, MAX_BODY_LENGTH) + "\n\n[Content truncated]";
    if (htmlContent.length > MAX_BODY_LENGTH) htmlContent = htmlContent.slice(0, MAX_BODY_LENGTH) + "\n\n[Content truncated]";

    // Construct form data
    const formData = new FormData();
    formData.append("worker_secret", EMAIL_HANDLER_SECRET); // Use the variable from env
    formData.append("user_email", senderEmail);

    const filename = `email-${Date.now()}.txt`;
    formData.append("chat_file", new Blob([chatContent], { type: "text/plain" }), filename);
    if (htmlContent) {
      formData.append("html_body", new Blob([htmlContent], { type: "text/html" }), filename.replace(".txt", ".html"));
    }

    for (const att of attachments) {
      formData.append("attachment", att.blob, att.filename);
    }

    // Upload to backend
    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;
      const uploadResponse = await fetch(uploadUrl, { method: "POST", body: formData });
      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error(`Worker Email Handler: Backend upload error: ${uploadResponse.status} ${uploadResponse.statusText}`, errorText);
      } else {
        console.log(`Worker Email Handler: Successfully uploaded email from ${senderEmail} with ${attachments.length} attachments.`);
      }
    } catch (error) {
      console.error("Worker Email Handler: API upload request failed. Error: " + error.message);
    }
  },
};