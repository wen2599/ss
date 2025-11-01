// worker/worker.js
// Version 2.0: Updated to correctly proxy the new api_router.php endpoint.

// ========== 1. Email Parsing and Helper Functions (No changes) ==========
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
  return input.replace(/=(?:\r\n|\n|\r)/g, '').replace(/=([A-Fa-f0-9]{2})/g, (m, hex) => String.fromCharCode(parseInt(hex, 16)));
}
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
function decodeSubject(subject) {
    if (!subject) return 'No Subject';
    return subject.replace(/=\?([^?]+)\?([BQ])\?([^?]+)\?=/gi, (match, charset, encoding, encodedText) => {
        try {
            const decoder = new TextDecoder(charset);
            if (encoding.toUpperCase() === 'B') {
                const binaryString = atob(encodedText);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) bytes[i] = binaryString.charCodeAt(i);
                return decoder.decode(bytes);
            } else if (encoding.toUpperCase() === 'Q') {
                return decoder.decode(new Uint8Array(decodeQuotedPrintable(encodedText.replace(/_/g, ' ')).split('').map(c => c.charCodeAt(0))));
            }
        } catch (e) {
            console.error(`Failed to decode subject part: ${match}`, e);
        }
        return encodedText;
    });
}
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
    let htmlPart = '', textPart = '';
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
          for (let i = 0; i < binaryString.length; i++) bytes[i] = binaryString.charCodeAt(i);
          decodedPartBody = decoder.decode(bytes);
        } catch(e) { decodedPartBody = partBody; }
      } else if (contentEncoding === 'quoted-printable') {
        decodedPartBody = decoder.decode(new Uint8Array(decodeQuotedPrintable(partBody).split('').map(c => c.charCodeAt(0))));
      } else {
        decodedPartBody = partBody;
      }
      if (partContentType.includes('text/html')) htmlPart = decodedPartBody;
      else if (partContentType.includes('text/plain')) textPart = decodedPartBody;
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
        for (let i = 0; i < binaryString.length; i++) bytes[i] = binaryString.charCodeAt(i);
        return decoder.decode(bytes);
      } catch(e) { return emailBody; }
    } else if (contentEncoding === 'quoted-printable') {
      return decoder.decode(new Uint8Array(decodeQuotedPrintable(emailBody).split('').map(c => c.charCodeAt(0))));
    }
    return emailBody;
  }
}

// ========== 2. Core Worker Logic ==========
export default {
  /**
   * Handles HTTP requests, acting as a proxy for the backend API.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = "https://wenge.cloudns.ch"; // Hardcoded for clarity

    // --- UPDATED ROUTING LOGIC ---
    // This condition now correctly proxies requests for the API router.
    if (url.pathname.startsWith('/api_router.php')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const backendRequest = new Request(backendUrl, request);
      return fetch(backendRequest);
    }

    // --- Fallback for other requests (e.g., frontend assets) ---
    // This logic should be updated if the worker is also serving the frontend.
    // For now, we'll return a generic not found.
    return new Response('Not found.', { status: 404 });
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
    const verificationUrl = `${PUBLIC_API_ENDPOINT}?action=is_user_registered&worker_secret=${EMAIL_HANDLER_SECRET}&email=${encodeURIComponent(senderEmail)}`;

    try {
      const verificationResponse = await fetch(verificationUrl);
      if (!verificationResponse.ok) {
        throw new Error(`Verification failed with status ${verificationResponse.status}`);
      }
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) {
        return; // Discard email
      }

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
      const postResponse = await fetch(postUrl, { method: 'POST', body: formData });

      if (!postResponse.ok) {
        const errorText = await postResponse.text();
        throw new Error(`Failed to post email with status ${postResponse.status}: ${errorText}`);
      }
    } catch (error) {
      console.error('Worker Email Handler Error:', error.message);
      throw error;
    }
  }
};
