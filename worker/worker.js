// worker/worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    // --- API Routing ---
    // All requests starting with /api/ are routed to the backend server.
    if (url.pathname.startsWith('/api/')) {
        const backendUrl = new URL(url.pathname, backendServer);
        backendUrl.search = url.search;

        const newHeaders = new Headers(request.headers);
        newHeaders.delete('Host');

        const backendRequest = new Request(backendUrl, {
            method: request.method,
            headers: newHeaders,
            body: request.body,
            duplex: 'half'
        });

        return fetch(backendRequest);
    }

    // For all other requests, serve the static assets from Cloudflare Pages.
    return env.ASSETS.fetch(request);
  },

  async email(message, env, ctx) {
    const { PUBLIC_API_ENDPOINT, EMAIL_HANDLER_SECRET } = env;

    if (!PUBLIC_API_ENDPOINT || !EMAIL_HANDLER_SECRET) {
      console.error("Worker Email Handler: Missing required environment variables.");
      return;
    }

    const senderEmail = message.from;

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

      const postUrl = `${PUBLIC_API_ENDPOINT}/api/emails`;

      const postResponse = await fetch(postUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(postData),
      });

      if (!postResponse.ok) {
        const errorText = await postResponse.text();
        throw new Error(`Failed to post email with status ${postResponse.status}`);
      }
    } catch (error) {
      console.error('Worker Email Handler: An unexpected error occurred:', error.message);
      throw error;
    }
  }
};

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

function decodeQuotedPrintable(input) {
  return input
    .replace(/=(?:\r\n|\n|\r)/g, '')
    .replace(/=([A-Fa-f0-9]{2})/g, (m, hex) => String.fromCharCode(parseInt(hex, 16)));
}

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
