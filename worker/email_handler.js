// This is a standalone Cloudflare Worker script for processing incoming emails.

// Helper function to convert a readable stream to a string
async function streamToString(stream) {
  const reader = stream.getReader();
  const decoder = new TextDecoder();
  let result = '';
  while (true) {
    const { done, value } = await reader.read();
    if (done) {
      break;
    }
    result += decoder.decode(value, { stream: true });
  }
  return result;
}

export default {
  /**
   * @param {EmailMessage} message
   * @param {object} env
   * @param {object} ctx
   */
  async email(message, env, ctx) {
    // These URLs must point directly to the backend server, including the custom port.
    // The worker will make direct requests to these endpoints.
    const VERIFY_USER_URL = "https://wenge.cloudns.ch/api/is_user_registered.php";
    const UPLOAD_API_URL = "https://wenge.cloudns.ch/api/api.php";
    // This secret must exactly match the secret in your PHP scripts.
    const WORKER_SECRET = "A_VERY_SECRET_KEY";

    // --- 1. Extract Sender Email ---
    const senderEmail = message.from;
    if (!senderEmail) {
        console.error("Email received with no 'From' address. Stopping processing.");
        return;
    }

    // --- 2. Verify if Sender is a Registered User ---
    try {
        const verificationUrl = `${VERIFY_USER_URL}?worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
        const verificationResponse = await fetch(verificationUrl);

        if (!verificationResponse.ok) {
             console.error(`User verification request failed with status: ${verificationResponse.status}.`);
             return;
        }

        const verificationData = await verificationResponse.json();

        if (!verificationData.success || !verificationData.is_registered) {
            console.log(`Email from unregistered user '${senderEmail}' was rejected by the backend.`);
            return;
        }
    } catch (error) {
        console.error("Failed to verify user email. Error: " + error.message);
        return;
    }

    console.log(`Email from registered user '${senderEmail}' accepted. Proceeding to upload.`);

    // --- 3. Process and Upload Email Content ---
    const formData = new FormData();
    const rawEmail = await streamToString(message.raw);

    // A simple regex to extract the plain text body.
    const textBodyMatch = rawEmail.match(/Content-Type: text\/plain;[\s\S]*?\r\n\r\n([\s\S]*)/);
    let chatContent = "Email did not contain a recognizable text part.";
    if (textBodyMatch && textBodyMatch[1]) {
      // More robustly find the end of the text part
      const endBoundaryMatch = textBodyMatch[1].match(/--[a-zA-Z0-9_.-]+/);
      chatContent = endBoundaryMatch ? textBodyMatch[1].substring(0, endBoundaryMatch.index).trim() : textBodyMatch[1].trim();
    }

    const blob = new Blob([chatContent], { type: 'text/plain' });
    const filename = `email-${message.headers.get("message-id") || new Date().toISOString()}.txt`;

    formData.append('chat_file', blob, filename);
    formData.append('worker_secret', WORKER_SECRET);
    formData.append('user_email', senderEmail);

    try {
      const uploadResponse = await fetch(UPLOAD_API_URL, {
        method: 'POST',
        body: formData,
      });
      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error(`Backend upload error: ${uploadResponse.status} ${uploadResponse.statusText}`, errorText);
      } else {
        console.log(`Successfully uploaded chat log for user ${senderEmail}.`);
      }
    } catch (error) {
      console.error("Failed to fetch upload API: " + error.message);
    }
  },
};
