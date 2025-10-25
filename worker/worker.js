// worker/worker.js (Complete version combining proxy and new email handler)

// ========== 1. Helper Functions for Email Parsing ==========

// ... (email parsing functions remain the same) ...
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
    .replace(/=(?:\r\n|\n|\r)/g, '') // soft line breaks
    .replace(/=([A-Fa-f0-9]{2})/g, (m, hex) => String.fromCharCode(parseInt(hex, 16)));
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

    // Route for the new AI processing action
    if (url.pathname === '/process-ai') {
        // ... (AI processing logic remains the same) ...
        if (request.method !== 'POST') {
            return new Response('Method Not Allowed', { status: 405 });
        }
        try {
            const { email_content } = await request.json();
            if (!email_content) {
                return new Response('Missing email_content in request body', { status: 400 });
            }
            const prompt = `...`; // Prompt is unchanged
            const response = await env.AI.run('@cf/meta/llama-2-7b-chat-int8', { prompt });
            const jsonMatch = response.response.match(/```json\n([\s\S]*?)\n```/);
            const jsonResponse = jsonMatch ? jsonMatch[1] : response.response;
            return new Response(jsonResponse, { headers: { 'Content-Type': 'application/json' } });
        } catch (error) {
            console.error('AI processing error:', error);
            return new Response(`Error processing AI request: ${error.message}`, { status: 500 });
        }
    }

    // Proxy API calls (e.g., /api/*) to the backend
    if (url.pathname.startsWith('/api/')) {
        const backendUrl = new URL(url.pathname, backendServer);
        backendUrl.search = url.search;

        const backendRequest = new Request(backendUrl, {
            method: request.method,
            headers: request.headers,
            body: request.body,
            duplex: 'half'
        });

        return fetch(backendRequest);
    }

    // For any other request, return a simple "Not Found".
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
    console.log(`Worker Email Handler: Received email from: ${senderEmail}`);

    try {
      const rawEmail = await streamToString(message.raw);
      const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
      const subject = decodeSubject(headers['subject']);
      const body = extractHTMLBody(rawEmail);

      const postData = {
          worker_secret: EMAIL_HANDLER_SECRET,
          from: senderEmail,
          to: message.to,
          subject: subject,
          body: body,
      };

      const postUrl = `${PUBLIC_API_ENDPOINT}/api/save-email.php`;
      console.log(`Worker Email Handler: Posting email content to: ${postUrl}`);

      const postResponse = await fetch(postUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(postData),
      });

      if (!postResponse.ok) {
        const errorText = await postResponse.text();
        console.error(`Worker Email Handler: Failed to forward email content. Status: ${postResponse.status}, Body: ${errorText}`);
        throw new Error(`Failed to post email with status ${postResponse.status}`);
      }

      const responseData = await postResponse.json();
      console.log('Worker Email Handler: Email forwarded successfully. Backend response:', JSON.stringify(responseData));

    } catch (error) {
      console.error('Worker Email Handler: An unexpected error occurred:', error.message);
      throw error;
    }
  }
};
