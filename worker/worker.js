// worker/worker.js (Corrected version: Proxy and Email Forwarder ONLY)

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

// 解析邮件头
function parseHeaders(headers) {
  const headerMap = {};
  const headerLines = headers.split('\r\n');
  let currentHeader = '';
  headerLines.forEach(line => {
    if (line.startsWith(' ') || line.startsWith('\t')) {
      headerMap[currentHeader] += ' ' + line.trim();
    } else if (line.includes(':')) {
      const parts = line.split(':');
      currentHeader = parts[0].toLowerCase();
      headerMap[currentHeader] = parts.slice(1).join(':').trim();
    }
  });
  return headerMap;
}

// 解析邮件主题
function decodeSubject(subject) {
    if (!subject) return 'No Subject';
    return subject.replace(/=\?([^?]+)\?([BQ])\?([^?]+)\?=/gi, (match, charset, encoding, encodedText) => {
        try {
            const decoder = new TextDecoder(charset);
            if (encoding.toUpperCase() === 'B') {
                const binaryString = atob(encodedText);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }
                return decoder.decode(bytes);
            } else if (encoding.toUpperCase() === 'Q') {
                return decoder.decode(
                    new Uint8Array(
                        decodeQuotedPrintable(encodedText.replace(/_/g, ' ')).split('').map(c => c.charCodeAt(0))
                    )
                );
            }
        } catch (e) {
            console.error(`Failed to decode subject part: ${match}`, e);
        }
        return encodedText;
    });
}

// ========== 2. Helper Function for Body Extraction ==========

function extractHTMLBody(rawEmail) {
  const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
  const contentType = headers['content-type'] || '';
  const bodyStartIndex = rawEmail.indexOf('\r\n\r\n');

  if (bodyStartIndex === -1) return '';
  const emailBody = rawEmail.substring(bodyStartIndex + 4);

  if (contentType.includes('multipart/')) {
    const boundaryMatch = contentType.match(/boundary="?([^"]+)"?/);
    if (!boundaryMatch) return emailBody;

    const boundary = `--${boundaryMatch[1]}`;
    const parts = emailBody.split(new RegExp(`\\s*${boundary}(--)?\\s*`));

    let htmlPart = '';
    let textPart = '';

    for (const part of parts) {
      if (typeof part !== 'string' || !part.trim()) continue;
      const partHeadersEndIndex = part.indexOf('\r\n\r\n');
      if (partHeadersEndIndex === -1) continue;

      const partHeadersRaw = part.substring(0, partHeadersEndIndex);
      const partBody = part.substring(partHeadersEndIndex + 4);
      const partHeaders = parseHeaders(partHeadersRaw);

      const partContentType = partHeaders['content-type'] || '';
      const contentEncoding = (partHeaders['content-transfer-encoding'] || '').toLowerCase();
      const charsetMatch = partContentType.match(/charset="?([^"]+)"?/i);
      const charset = charsetMatch ? charsetMatch[1].toLowerCase() : 'utf-8';

      let decodedPartBody;
      try {
        const decoder = new TextDecoder(charset);
        if (contentEncoding === 'base64') {
          const binaryString = atob(partBody.replace(/(\r\n|\n|\r)/gm, ""));
          const bytes = new Uint8Array(binaryString.length);
          for (let i = 0; i < binaryString.length; i++) {
              bytes[i] = binaryString.charCodeAt(i);
          }
          decodedPartBody = decoder.decode(bytes);
        } else if (contentEncoding === 'quoted-printable') {
          const decodedBytes = new Uint8Array(decodeQuotedPrintable(partBody).split('').map(c => c.charCodeAt(0)));
          decodedPartBody = decoder.decode(decodedBytes);
        } else {
          decodedPartBody = partBody;
        }
      } catch (e) {
        console.error("Decoding failed:", e);
        decodedPartBody = partBody; // Fallback
      }

      if (partContentType.includes('text/html')) htmlPart = decodedPartBody;
      else if (partContentType.includes('text/plain')) textPart = decodedPartBody;
    }
    return htmlPart || textPart || emailBody;
  } else {
    // Not multipart, decode the whole body
    const contentEncoding = (headers['content-transfer-encoding'] || '').toLowerCase();
    const charsetMatch = contentType.match(/charset="?([^"]+)"?/i);
    const charset = charsetMatch ? charsetMatch[1].toLowerCase() : 'utf-8';
    try {
        const decoder = new TextDecoder(charset);
        if (contentEncoding === 'base64') {
            const binaryString = atob(emailBody.replace(/(\r\n|\n|\r)/gm, ""));
            const bytes = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            return decoder.decode(bytes);
        } else if (contentEncoding === 'quoted-printable') {
            const decodedBytes = new Uint8Array(decodeQuotedPrintable(emailBody).split('').map(c => c.charCodeAt(0)));
            return decoder.decode(decodedBytes);
        }
    } catch(e) {
        return emailBody; // Fallback
    }
    return emailBody;
  }
}

// ========== 3. Core Worker Logic ==========

export default {
  /**
   * Handles HTTP requests, acting as a proxy for the backend API.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT ? new URL(env.PUBLIC_API_ENDPOINT).origin : "https://wenge.cloudns.ch";

    const backendUrl = new URL(url.pathname, backendServer);
    backendUrl.search = url.search;

    const requestInit = {
      method: request.method,
      headers: request.headers,
      redirect: 'follow',
    };

    // Explicitly handle body and duplex for requests that might have a body
    if (request.body) {
      requestInit.body = request.body;
      requestInit.duplex = 'half'; // CRUCIAL for streaming bodies in Workers
    }

    const backendRequest = new Request(backendUrl, requestInit);
    return fetch(backendRequest);
  },

  /**
   * Handles incoming email messages.
   */
  async email(message, env, ctx) {
    const { PUBLIC_API_ENDPOINT, EMAIL_HANDLER_SECRET } = env;

    if (!PUBLIC_API_ENDPOINT || !EMAIL_HANDLER_SECRET) {
      console.error("Worker Email Handler: Missing required environment variables.");
      return;
    }

    const senderEmail = message.from;
    try {
      // Step 1: Verify if the user is registered.
      const verificationUrl = `${PUBLIC_API_ENDPOINT}?action=is_user_registered&worker_secret=${EMAIL_HANDLER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);
      if (!verificationResponse.ok) {
        throw new Error(`Verification failed with status ${verificationResponse.status}`);
      }
      
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) {
        console.log(`User ${senderEmail} is not registered. Discarding email.`);
        return;
      }

      // Step 2: If verified, parse and forward the email.
      const rawEmail = await streamToString(message.raw);
      const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
      const subject = decodeSubject(headers['subject']);
      const body = extractHTMLBody(rawEmail); // This is the clean HTML/text body

      // Send both the clean body and the raw email for full context
      const formData = new FormData();
      formData.append('worker_secret', EMAIL_HANDLER_SECRET);
      formData.append('from_address', senderEmail);
      formData.append('to_address', message.to);
      formData.append('subject', subject);
      formData.append('body', body);
      formData.append('raw_email', rawEmail); // Send raw email for storage
      
      const postUrl = `${PUBLIC_API_ENDPOINT}?action=save_email`;
      const postResponse = await fetch(postUrl, {
        method: 'POST',
        body: formData,
      });

      if (!postResponse.ok) {
        const errorText = await postResponse.text();
        throw new Error(`Failed to post email. Status: ${postResponse.status}, Body: ${errorText}`);
      }
      console.log('Email from ' + senderEmail + ' forwarded successfully.');

    } catch (error) {
      console.error('Worker Email Handler Error:', error.message);
      throw error; // Re-throw to signal a retry
    }
  }
};