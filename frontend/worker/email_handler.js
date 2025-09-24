// worker/email_handler.js (Ultimate Edition: Raw Buffer Parsing)

// ========== 辅助函数区域 ==========

// 将 ReadableStream 转为 ArrayBuffer
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

// 在 Uint8Array 中查找子数组
function findNeedle(haystack, needle, offset = 0) {
    for (let i = offset; i <= haystack.length - needle.length; i++) {
        let found = true;
        for (let j = 0; j < needle.length; j++) {
            if (haystack[i + j] !== needle[j]) {
                found = false;
                break;
            }
        }
        if (found) return i;
    }
    return -1;
}

function parseEmail(rawBuffer) {
    const rawUint8 = new Uint8Array(rawBuffer);
    const textDecoder = new TextDecoder(); // for headers
    const headersPart = textDecoder.decode(rawUint8.slice(0, 1024));

    const boundaryMatch = headersPart.match(/boundary="?([^"]+)"?/i);
    if (!boundaryMatch) return null;

    const boundary = `--${boundaryMatch[1]}`;
    const boundaryBytes = new TextEncoder().encode(boundary);

    let parts = [];
    let lastPos = 0;
    while(lastPos < rawUint8.length) {
        let start = findNeedle(rawUint8, boundaryBytes, lastPos);
        if (start === -1) break;
        start += boundaryBytes.length;
        let end = findNeedle(rawUint8, boundaryBytes, start);
        if (end === -1) end = rawUint8.length;
        parts.push(rawUint8.slice(start, end));
        lastPos = end;
    }

    for (const part of parts) {
        const partStr = textDecoder.decode(part);
        const headersMatch = partStr.match(/^([\s\S]*?)\r?\n\r?\n/);
        if (!headersMatch) continue;

        const headers = headersMatch[1];
        const contentTypeHeader = headers.match(/Content-Type:\s*text\/plain/i);
        const contentDispositionHeader = headers.match(/Content-Disposition:\s*attachment/i);

        if (contentTypeHeader && !contentDispositionHeader) {
            const bodyOffset = headers.length + 2; // +2 for \r\n
            const bodyBytes = part.slice(bodyOffset);

            const charsetMatch = headers.match(/charset="?([^"]+)"?/i);
            const charset = charsetMatch ? charsetMatch[1].trim() : 'utf-8';

            return { raw_body_bytes: bodyBytes, charset: charset };
        }
    }
    return null;
}

// ========== 核心处理逻辑 ==========

export default {
  async email(message, env, ctx) {
    const PUBLIC_API_ENDPOINT = "https://ss.wenxiuxiu.eu.org";
    const WORKER_SECRET = "816429fb-1649-4e48-9288-7629893311a6";

    const senderEmail = message.from;
    if (!senderEmail) { return; }

    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/is_user_registered?worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);
      if (!verificationResponse.ok) { return; }
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) { return; }
    } catch (error) { return; }

    try {
      const rawEmailBuffer = await streamToArrayBuffer(message.raw);
      const emailPart = parseEmail(rawEmailBuffer);

      if (emailPart) {
        const formData = new FormData();
        formData.append("worker_secret", WORKER_SECRET);
        formData.append("user_email", senderEmail);
        formData.append("charset", emailPart.charset);
        formData.append("email_part_file", new Blob([emailPart.raw_body_bytes]), "email_part.txt");

        const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;
        await fetch(uploadUrl, { method: "POST", body: formData });
      }
    } catch (error) {
      console.error("Failed to process/forward email: " + error.message);
    }
  },
};