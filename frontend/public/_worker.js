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
  let textContent = '';
  let htmlContent = '';
  const attachments = [];
  let attachmentCount = 0;

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

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    if (pathname.startsWith('/api/')) {
      if (!env.PUBLIC_API_ENDPOINT || !env.WORKER_SECRET) {
        return new Response('Worker is not configured for API proxying.', { status: 500 });
      }

      const backendPath = pathname.substring(5);
      const backendUrl = new URL(backendPath + url.search, env.PUBLIC_API_ENDPOINT);

      const backendRequest = new Request(backendUrl, request);
      backendRequest.headers.set('X-Worker-Secret', env.WORKER_SECRET);
      backendRequest.headers.set('Host', new URL(env.PUBLIC_API_ENDPOINT).host);

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

    return env.ASSETS.fetch(request);
  },

  async email(message, env, ctx) {
    if (!env.PUBLIC_API_ENDPOINT || !env.WORKER_SECRET) {
      console.error("Worker is not configured for email processing.");
      return;
    }

    const senderEmail = message.from || "";
    if (!senderEmail) {
      console.error("Received email without a sender address.");
      return;
    }

    const rawEmail = await streamToString(message.raw);
    const parsed = parseEmail(rawEmail);

    const emailData = {
        from: message.from,
        to: message.to,
        headers: Object.fromEntries(message.headers),
        textContent: parsed.textContent,
        htmlContent: parsed.htmlContent,
        attachments: parsed.attachments.map(att => ({ // Send metadata only
            filename: att.filename,
            contentType: att.contentType,
            size: att.blob.size,
        })),
    };

    const formData = new FormData();
    formData.append("worker_secret", env.WORKER_SECRET);
    formData.append("user_email", senderEmail);
    formData.append("email_data", JSON.stringify(emailData));

    // Append attachment files
    for (const att of parsed.attachments) {
        formData.append(att.filename, att.blob);
    }

    try {
      const uploadUrl = new URL('store_email.php', env.PUBLIC_API_ENDPOINT).toString();
      await fetch(uploadUrl, {
        method: "POST",
        body: formData,
      });
    } catch (error) {
      console.error("Failed to store email:", error);
    }
  },
};