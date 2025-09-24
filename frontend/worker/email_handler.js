// worker/email_handler.js (Final Correct Version)

async function streamToString(stream) {
    const reader = stream.getReader();
    // Use a decoder that is less likely to corrupt bytes, like latin1
    const decoder = new TextDecoder('iso-8859-1');
    let result = '';
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        result += decoder.decode(value, { stream: true });
    }
    return result;
}

function parseEmailForContent(rawEmail) {
    const boundaryMatch = rawEmail.match(/boundary="?([^"]+)"?/i);
    if (!boundaryMatch) {
        // Not a multipart message, assume the whole body is the content
        return { encoded_body: rawEmail, transfer_encoding: '7bit', charset: 'utf-8' };
    }
    const boundary = boundaryMatch[1];
    const parts = rawEmail.split(new RegExp(`--${boundary}(--)?`));

    for (const part of parts) {
        if (!part.trim()) continue;

        const headersMatch = part.match(/^([\s\S]*?)\r?\n\r?\n/);
        if (!headersMatch) continue;
        const headers = headersMatch[1];

        const contentTypeHeader = headers.match(/Content-Type:\s*text\/plain/i);
        const contentDispositionHeader = headers.match(/Content-Disposition:\s*attachment/i);

        // We only want the main plain text part, not attachments
        if (contentTypeHeader && !contentDispositionHeader) {
            const body = part.substring(headers.length).trim();
            const charsetMatch = headers.match(/charset="?([^"]+)"?/i);
            const encodingMatch = headers.match(/Content-Transfer-Encoding:\s*(\S+)/i);

            return {
                encoded_body: body,
                transfer_encoding: encodingMatch ? encodingMatch[1].trim().toLowerCase() : '7bit',
                charset: charsetMatch ? charsetMatch[1].trim() : 'utf-8'
            };
        }
    }
    return null; // No suitable part found
}

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
    } catch (error) {
      console.error("Failed to verify user email: " + error.message);
      return;
    }

    try {
      const rawEmailString = await streamToString(message.raw);
      const emailParts = parseEmailForContent(rawEmailString);

      if (emailParts) {
        const formData = new FormData();
        formData.append("worker_secret", WORKER_SECRET);
        formData.append("user_email", senderEmail);
        formData.append("encoded_body", emailParts.encoded_body);
        formData.append("transfer_encoding", emailParts.transfer_encoding);
        formData.append("charset", emailParts.charset);

        const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;
        const uploadResponse = await fetch(uploadUrl, {
          method: "POST",
          body: formData,
        });

        if (!uploadResponse.ok) {
            const errorText = await uploadResponse.text();
            console.error(`Backend upload error: ${uploadResponse.status} ${uploadResponse.statusText}`, errorText);
        } else {
            console.log(`Successfully sent parsed content from ${senderEmail} to backend.`);
        }
      } else {
        console.error("Could not find a suitable text/plain part in the email.");
      }
    } catch (error) {
      console.error("Failed to process and forward email: " + error.message);
    }
  },
};