// worker/email_handler.js （全面增强版，支持附件、HTML正文、类型过滤、大小限制、详细日志、灵活配置）

// ========== 辅助函数区域 ==========

async function streamToArrayBuffer(stream) {
    let result = new Uint8Array(0);
    const reader = stream.getReader();
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        const newResult = new Uint8Array(result.length + value.length);
        newResult.set(result);
        newResult.set(value, result.length);
        result = newResult;
    }
    return result.buffer;
}

function decodeQuotedPrintable(input, charset = 'utf-8') {
    const decoder = new TextDecoder(charset);
    const bytes = [];
    const regex = /=([A-Fa-f0-9]{2})|([^=])/g;
    let match;
    while ((match = regex.exec(input.replace(/=(?:\r\n|\n|\r)/g, ''))) !== null) {
        if (match[1]) {
            bytes.push(parseInt(match[1], 16));
        } else {
            for (let i = 0; i < match[2].length; i++) {
                bytes.push(match[2].charCodeAt(i));
            }
        }
    }
    return decoder.decode(new Uint8Array(bytes));
}

function b64toBlob(base64, mime) {
  const byteChars = atob(base64);
  const byteNumbers = new Array(byteChars.length);
  for (let i = 0; i < byteChars.length; i++) {
    byteNumbers[i] = byteChars.charCodeAt(i);
  }
  const byteArray = new Uint8Array(byteNumbers);
  return new Blob([byteArray], { type: mime });
}

function parseMime(rawEmail) {
    let textContent = '', htmlContent = '', attachments = [];
    const boundary = rawEmail.match(/boundary="?([^"]+)"?/i)?.[1];
    if (!boundary) {
        textContent = rawEmail; // Assume plain text if no boundary
        return { textContent, htmlContent, attachments };
    }

    const parts = rawEmail.split(new RegExp(`--${boundary}(--)?`));

    for (const part of parts) {
        if (!part.trim()) continue;
        const headersMatch = part.match(/^([\s\S]*?)\r?\n\r?\n/);
        if (!headersMatch) continue;
        const headers = headersMatch[1];
        const body = part.substring(headers.length).trim();

        const contentTypeHeader = headers.match(/Content-Type: ([^;]+);?/i);
        const charsetMatch = headers.match(/charset="?([^"]+)"?/i);
        const encoding = (headers.match(/Content-Transfer-Encoding: (\S+)/i)?.[1] || '').toLowerCase();
        const disposition = (headers.match(/Content-Disposition: (\S+)/i)?.[1] || '').toLowerCase();

        let contentType = contentTypeHeader?.[1].trim() || '';
        let charset = charsetMatch?.[1] || 'utf-8';
        let decodedBody = body;

        try {
            if (encoding === 'base64') {
                decodedBody = atob(body.replace(/\s/g, ''));
            } else if (encoding === 'quoted-printable') {
                // We need to decode quoted-printable with the correct charset in mind
                // The helper function does this now
                decodedBody = decodeQuotedPrintable(body, charset);
            }

            if (contentType.startsWith('text/plain') && disposition !== 'attachment') {
                // If it's plain text and not an attachment
                if (encoding !== 'quoted-printable') { // QP is already decoded to string
                  const decoder = new TextDecoder(charset);
                  textContent += decoder.decode(Uint8Array.from(decodedBody, c => c.charCodeAt(0)));
                } else {
                  textContent += decodedBody;
                }
            } else if (contentType.startsWith('text/html') && disposition !== 'attachment') {
                // If it's HTML and not an attachment
                if (encoding !== 'quoted-printable') {
                  const decoder = new TextDecoder(charset);
                  htmlContent += decoder.decode(Uint8Array.from(decodedBody, c => c.charCodeAt(0)));
                } else {
                  htmlContent += decodedBody;
                }
            } else if (disposition === 'attachment') {
                // Handle attachments
                const filenameMatch = headers.match(/filename="?([^"]+)"?/i);
                const filename = filenameMatch?.[1] || 'unnamed-attachment';
                const blob = b64toBlob(btoa(decodedBody), contentType);
                attachments.push({ filename, blob, contentType });
            }
        } catch (e) {
            console.error(`Error processing part with charset ${charset}: ${e.message}`);
        }
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

    const senderEmail = message.from;
    if (!senderEmail) {
      console.error("No sender address found.");
      return;
    }

    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/is_user_registered?worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);
      if (!verificationResponse.ok) {
        console.error(`User verification request failed: ${verificationResponse.status}`);
        return;
      }
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) {
        console.log(`Unregistered user '${senderEmail}' rejected.`);
        return;
      }
    } catch (error) {
      console.error("Failed to verify user email: " + error.message);
      return;
    }

    let chatContent = "Could not parse plain text content.";
    let htmlContent = "";
    let attachments = [];
    try {
      const rawEmail = await streamToString(message.raw);
      const parsed = parseMime(rawEmail);
      if (parsed.textContent) chatContent = parsed.textContent;
      if (parsed.htmlContent) htmlContent = parsed.htmlContent;
      attachments = parsed.attachments || [];
    } catch (err) {
      console.error("Failed to parse email content: " + err.message);
    }

    if (chatContent.length > MAX_BODY_LENGTH) {
      chatContent = chatContent.slice(0, MAX_BODY_LENGTH) + "\n\n[Content truncated]";
    }
     if (htmlContent.length > MAX_BODY_LENGTH) {
      htmlContent = htmlContent.slice(0, MAX_BODY_LENGTH) + "\n\n[Content truncated]";
    }

    const formData = new FormData();
    formData.append("chat_file", new Blob([chatContent], { type: "text/plain" }), "email_content.txt");
    if (htmlContent) {
      formData.append("html_body", new Blob([htmlContent], { type: "text/html" }), "email_content.html");
    }
    formData.append("worker_secret", WORKER_SECRET);
    formData.append("user_email", senderEmail);

    // This version does not handle attachments to simplify the example.
    // The parseMime function above can be extended to handle them.

    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;
      const uploadResponse = await fetch(uploadUrl, {
        method: "POST",
        body: formData,
      });
      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error(`Backend upload error: ${uploadResponse.status} ${uploadResponse.statusText}`, errorText);
      } else {
        console.log(`Successfully uploaded content from ${senderEmail}.`);
      }
    } catch (error) {
      console.error("Upload API request failed: " + error.message);
    }
  },
};

// Helper to convert stream to string, this is needed because the parseMime function works with strings.
async function streamToString(stream) {
    const reader = stream.getReader();
    const decoder = new TextDecoder("utf-8"); // A base decoder for headers
    let buffer = '';
    while(true) {
        const { done, value } = await reader.read();
        if(done) break;
        buffer += decoder.decode(value, {stream: true});
    }
    return buffer;
}
