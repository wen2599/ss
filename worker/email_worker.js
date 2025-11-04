// worker/worker.js (Version without AI functionality)

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

/**
 * Parses a raw email string to find and decode the HTML body.
 * @param {string} rawEmail - The full raw email content.
 * @returns {string} The decoded HTML body, or a fallback.
 */
function extractHTMLBody(rawEmail) {
  const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
  const contentType = headers['content-type'] || '';
  const bodyStartIndex = rawEmail.indexOf('\r\n\r\n');

  if (bodyStartIndex === -1) {
    return '';
  }
  const emailBody = rawEmail.substring(bodyStartIndex + 4);

  if (contentType.includes('multipart/')) {
    const boundaryMatch = contentType.match(/boundary="?([^"]+)"?/);
    if (!boundaryMatch) {
      return emailBody;
    }

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
      const decoder = new TextDecoder(charset);

      if (contentEncoding === 'base64') {
        try {
          const binaryString = atob(partBody.replace(/(\r\n|\n|\r)/gm, ""));
          const bytes = new Uint8Array(binaryString.length);
          for (let i = 0; i < binaryString.length; i++) {
              bytes[i] = binaryString.charCodeAt(i);
          }
          decodedPartBody = decoder.decode(bytes);
        } catch(e) {
          console.error("Base64 decoding with charset failed:", e);
          decodedPartBody = partBody;
        }
      } else if (contentEncoding === 'quoted-printable') {
        const decodedBytes = new Uint8Array(
          decodeQuotedPrintable(partBody).split('').map(c => c.charCodeAt(0))
        );
        decodedPartBody = decoder.decode(decodedBytes);
      } else {
        decodedPartBody = partBody;
      }

      if (partContentType.includes('text/html')) {
        htmlPart = decodedPartBody;
      } else if (partContentType.includes('text/plain')) {
        textPart = decodedPartBody;
      }
    }
    return htmlPart || textPart || emailBody;
  } else {
    const contentEncoding = (headers['content-transfer-encoding'] || '').toLowerCase();
    const charsetMatch = contentType.match(/charset="?([^"]+)"?/i);
    const charset = charsetMatch ? charsetMatch[1].toLowerCase() : 'utf-8';
    const decoder = new TextDecoder(charset);

    if (contentEncoding === 'base64') {
      try {
        const binaryString = atob(emailBody.replace(/(\r\n|\n|\r)/gm, ""));
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return decoder.decode(bytes);
      } catch(e) {
        return emailBody;
      }
    } else if (contentEncoding === 'quoted-printable') {
      const decodedBytes = new Uint8Array(
        decodeQuotedPrintable(emailBody).split('').map(c => c.charCodeAt(0))
      );
      return decoder.decode(decodedBytes);
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

    // Proxy API calls (e.g., /login.php, /api/users, etc.) to the backend
    if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api/')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const backendRequest = new Request(backendUrl, request);
      return fetch(backendRequest);
    }

    // For any other request, return "Not Found".
    return new Response('Not found.', { status: 404 });
  },

  /**
   * Handles incoming email messages.
   */
  async email(message, env, ctx) {
    const { PUBLIC_API_ENDPOINT, EMAIL_HANDLER_SECRET } = env;

    if (!PUBLIC_API_ENDPOINT || !EMAIL_HANDLER_SECRET) {
      console.error("Worker Email Handler: Missing required environment variables (PUBLIC_API_ENDPOINT or EMAIL_HANDLER_SECRET).");
      return;
    }

    const senderEmail = message.from;
    console.log(`Worker Email Handler: Received email from: ${senderEmail}`);

    try {
      // Step 1: Verify if the user is registered in the backend.
      const verificationUrl = `${PUBLIC_API_ENDPOINT}?action=is_user_registered&worker_secret=${EMAIL_HANDLER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      console.log(`Worker Email Handler: Calling verification URL: ${verificationUrl}`);
      
      const verificationResponse = await fetch(verificationUrl);

      if (!verificationResponse.ok) {
        console.error(`Worker Email Handler: User verification request failed with status: ${verificationResponse.status}.`);
        if (verificationResponse.status >= 400 && verificationResponse.status < 500) {
           return;
        }
        throw new Error(`Verification failed with status ${verificationResponse.status}`);
      }
      
      const verificationData = await verificationResponse.json();
      console.log("Worker Email Handler: Verification response received:", JSON.stringify(verificationData));

      if (!verificationData.success || !verificationData.is_registered) {
        console.log(`Worker Email Handler: User ${senderEmail} is not registered or verification failed. Discarding email.`);
        return;
      }

      console.log(`Worker Email Handler: User ${senderEmail} is verified. Proceeding to forward email.`);

      // Step 2: If user is verified, parse and forward the email content.
      const rawEmail = await streamToString(message.raw);
      const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
      const subject = decodeSubject(headers['subject']);
      const body = extractHTMLBody(rawEmail);

      const formData = new FormData();
      formData.append('worker_secret', EMAIL_HANDLER_SECRET);
      formData.append('from', senderEmail);
      formData.append('to', message.to);
      formData.append('subject', subject);
      formData.append('body', body);
      
      const postUrl = `${PUBLIC_API_ENDPOINT}?action=process_email`;
      console.log(`Worker Email Handler: Posting email content to: ${postUrl}`);

      const postResponse = await fetch(postUrl, {
        method: 'POST',
        body: formData,
      });

      if (!postResponse.ok) {
        const errorText = await postResponse.text();
        console.error(`Worker Email Handler: Failed to forward email content. Status: ${postResponse.status}, Body: ${errorText}`);
        throw new Error(`Failed to post email with status ${postResponse.status}`);
      }

      const postData = await postResponse.json();
      console.log('Worker Email Handler: Email forwarded successfully. Backend response:', JSON.stringify(postData));

    } catch (error) {
      console.error('Worker Email Handler: An unexpected error occurred:', error.message);
      throw error;
    }
  }
};