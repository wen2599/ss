// --- Constants ---
// This secret must be consistent and match the one in the backend .env file.
const WORKER_SECRET = "816429fb-1649-4e48-9288-7629893311a6";
const PUBLIC_API_ENDPOINT = "https://ss.wenxiuxiu.eu.org";

// --- Utility Functions for Email Parsing ---

function detectEncoding(uint8arr) {
  try {
    new TextDecoder('utf-8', { fatal: true }).decode(uint8arr);
    return 'utf-8';
  } catch {}
  return 'gb18030';
}

function decodeWithAutoEncoding(uint8arr) {
  const encoding = detectEncoding(uint8arr);
  try {
    return new TextDecoder(encoding, { fatal: false }).decode(uint8arr);
  } catch {
    return new TextDecoder('utf-8').decode(uint8arr);
  }
}

async function streamToString(stream) {
  const chunks = [];
  const reader = stream.getReader();
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    chunks.push(value);
  }
  let totalLen = chunks.reduce((acc, arr) => acc + arr.length, 0);
  let buffer = new Uint8Array(totalLen);
  let offset = 0;
  for (let arr of chunks) {
    buffer.set(arr, offset);
    offset += arr.length;
  }
  return decodeWithAutoEncoding(buffer);
}

function decodeQuotedPrintable(input) {
  return input
    .replace(/=(?:\r\n|\n|\r)/g, '')
    .replace(/=([A-Fa-f0-9]{2})/g, (m, hex) => String.fromCharCode(parseInt(hex, 16)));
}

function b64toUint8Array(base64) {
  const binary = atob(base64);
  const array = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) array[i] = binary.charCodeAt(i);
  return array;
}

function parseEmail(rawEmail, options = {}) {
    const maxAttachmentCount = options.maxAttachmentCount || 10;
    const maxAttachmentSize = options.maxAttachmentSize || 8 * 1024 * 1024;
    const allowedAttachmentTypes = options.allowedAttachmentTypes || ["image/", "application/pdf", "text/plain", "application/zip"];
    const boundaryMatch = rawEmail.match(/boundary="([^"]+)"/i) || rawEmail.match(/boundary=([^\r\n;]+)/i);
    const boundary = boundaryMatch ? boundaryMatch[1] : null;
    let textContent = '', htmlContent = '', attachments = [], attachmentCount = 0;

    function getCharset(header) {
        const m = header.match(/charset="?([^\s"]+)/i);
        if (m) {
            let cs = m[1].toLowerCase();
            if (/gbk|gb2312|gb18030/i.test(cs)) return 'gb18030';
            return cs;
        }
        return null;
    }

    if (boundary) {
        const parts = rawEmail.split(new RegExp(`--${boundary}(?:--)?`, 'g')).filter(Boolean);
        for (const part of parts) {
            const h = part.split(/\r?\n\r?\n/)[0];
            const charset = getCharset(h) || 'utf-8';
            if (/Content-Type:\s*text\/plain/i.test(part) && !/Content-Disposition:\s*attachment/i.test(part)) {
                if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
                    const base64Match = part.match(/\r?\n\r?\n([^]*)/);
                    if (base64Match) textContent += new TextDecoder(charset).decode(b64toUint8Array(base64Match[1].replace(/\r?\n/g, '')));
                } else if (/Content-Transfer-Encoding:\s*quoted-printable/i.test(part)) {
                    const qpMatch = part.match(/\r?\n\r?\n([^]*)/);
                    if (qpMatch) textContent += new TextDecoder(charset).decode(new TextEncoder().encode(decodeQuotedPrintable(qpMatch[1])));
                } else {
                    const plainMatch = part.match(/\r?\n\r?\n([^]*)/);
                    if (plainMatch) textContent += new TextDecoder(charset).decode(new TextEncoder().encode(plainMatch[1].trim()));
                }
            }
            if (/Content-Type:\s*text\/html/i.test(part) && !/Content-Disposition:\s*attachment/i.test(part)) {
                 let html = '';
                 if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
                    const base64Match = part.match(/\r?\n\r?\n([^]*)/);
                    if (base64Match) html += new TextDecoder(charset).decode(b64toUint8Array(base64Match[1].replace(/\r?\n/g, '')));
                } else if (/Content-Transfer-Encoding:\s*quoted-printable/i.test(part)) {
                    const qpMatch = part.match(/\r?\n\r?\n([^]*)/);
                    if (qpMatch) html += new TextDecoder(charset).decode(new TextEncoder().encode(decodeQuotedPrintable(qpMatch[1])));
                } else {
                    const htmlMatch = part.match(/\r?\n\r?\n([^]*)/);
                    if (htmlMatch) html += new TextDecoder(charset).decode(new TextEncoder().encode(htmlMatch[1].trim()));
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
                if (/Content-Transfer-Encoding:\s*base64/i.test(part)) {
                    const base64Match = part.match(/\r?\n\r?\n([^]*)/);
                    if (base64Match) {
                        const content = base64Match[1].replace(/\r?\n/g, '');
                        try {
                            let blob = new Blob([b64toUint8Array(content)], { type: contentType });
                            if (blob.size <= maxAttachmentSize) attachments.push({ filename, blob, contentType });
                        } catch {}
                    }
                } else {
                    const plainMatch = part.match(/\r?\n\r?\n([^]*)/);
                    if (plainMatch) {
                        let blob = new Blob([plainMatch[1]], { type: contentType });
                        if (blob.size <= maxAttachmentSize) attachments.push({ filename, blob, contentType });
                    }
                }
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

// --- Main Worker Export ---

export default {
  /**
   * Handles incoming HTTP requests (for the website and API proxy).
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    // API routes that the frontend will call
    const API_ROUTES = [
        '/register', '/login', '/logout',
        '/check_session', '/process_email'
    ];

    if (API_ROUTES.some(route => pathname.startsWith(route))) {
        const backendUrl = new URL(pathname + url.search, PUBLIC_API_ENDPOINT);
        const backendRequest = new Request(backendUrl, request);
        backendRequest.headers.set('X-Worker-Secret', WORKER_SECRET);
        backendRequest.headers.set('Host', new URL(PUBLIC_API_ENDPOINT).host);

        try {
            const response = await fetch(backendRequest);
            const newResponse = new Response(response.body, response);
            newResponse.headers.set('Access-Control-Allow-Origin', url.origin);
            newResponse.headers.set('Access-Control-Allow-Credentials', 'true');
            return newResponse;
        } catch (e) {
            return new Response(`Error proxying to backend: ${e.message}`, { status: 502 });
        }
    }

    // For all other requests, serve the static assets from the Pages build.
    return env.ASSETS.fetch(request);
  },

  /**
   * Handles incoming emails.
   */
  async email(message, env, ctx) {
    const MAX_BODY_LENGTH = 32 * 1024;
    const MAX_ATTACHMENT_COUNT = 10;
    const MAX_ATTACHMENT_SIZE = 8 * 1024 * 1024;
    const ALLOWED_ATTACHMENT_TYPES = ["image/", "application/pdf", "text/plain", "application/zip"];

    const senderEmail = message.from || (message.headers && message.headers.get("from")) || "";
    if (!senderEmail) return;

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
      const parsed = parseEmail(rawEmail, { maxAttachmentCount: MAX_ATTACHMENT_COUNT, maxAttachmentSize: MAX_ATTACHMENT_SIZE, allowedAttachmentTypes: ALLOWED_ATTACHMENT_TYPES });
      if (parsed.textContent) chatContent = parsed.textContent;
      if (parsed.htmlContent) htmlContent = parsed.htmlContent;
      attachments = parsed.attachments || [];
    } catch (err) {}

    if (chatContent.length > MAX_BODY_LENGTH) chatContent = chatContent.slice(0, MAX_BODY_LENGTH) + "\n\n[内容过长，已被截断]";
    if (htmlContent.length > MAX_BODY_LENGTH) htmlContent = htmlContent.slice(0, MAX_BODY_LENGTH) + "\n\n[内容过长，已被截断]";

    const messageId = (message.headers && message.headers.get("message-id")) || "";
    const safeEmail = senderEmail.replace(/[^a-zA-Z0-9_.-]/g, "_");
    const filename = `email-${safeEmail}-${Date.now()}${messageId ? "-" + messageId : ""}.txt`;

    const formData = new FormData();
    formData.append("worker_secret", WORKER_SECRET);
    formData.append("user_email", senderEmail);
    formData.append("raw_email_file", new Blob([chatContent], { type: "text/plain" }), filename);
    if (htmlContent) formData.append("html_body", new Blob([htmlContent], { type: "text/html" }), filename.replace(".txt", ".html"));
    for (const att of attachments) formData.append("attachment", att.blob, att.filename);

    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;
      await fetch(uploadUrl, { method: "POST", body: formData });
    } catch (error) {}
  },
};