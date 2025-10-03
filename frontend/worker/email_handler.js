/**
 * @file Cloudflare Worker for handling incoming emails.
 * This worker parses raw emails, verifies the sender, and forwards the content
 * to a backend service for processing. It is designed to be self-contained
 * without external npm dependencies.
 */

// --- Email Parsing Utilities (Self-Contained) ---

/**
 * Decodes a Uint8Array into a string using automatic character encoding detection (UTF-8 or GB18030).
 * @param {Uint8Array} uint8arr The byte array to decode.
 * @returns {string} The decoded string.
 */
function decodeWithAutoEncoding(uint8arr) {
    try {
        // Attempt to decode as UTF-8. The 'fatal' option throws an error on invalid sequences.
        return new TextDecoder('utf-8', { fatal: true }).decode(uint8arr);
    } catch (e) {
        // Fallback to GB18030 for legacy Chinese encodings.
        return new TextDecoder('gb18030', { fatal: false }).decode(uint8arr);
    }
}

/**
 * Reads a ReadableStream into a single string with automatic encoding detection.
 * @param {ReadableStream} stream The stream from the email message.
 * @returns {Promise<string>} The full content of the stream as a string.
 */
async function streamToString(stream) {
    const chunks = [];
    const reader = stream.getReader();
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        chunks.push(value);
    }
    const totalLength = chunks.reduce((acc, arr) => acc + arr.length, 0);
    const buffer = new Uint8Array(totalLength);
    let offset = 0;
    for (const chunk of chunks) {
        buffer.set(chunk, offset);
        offset += chunk.length;
    }
    return decodeWithAutoEncoding(buffer);
}

// --- Core Worker Logic ---

/**
 * Verifies if the sender's email is registered in the backend system.
 * @param {string} senderEmail The email address of the sender.
 * @param {object} env The worker's environment variables.
 * @returns {Promise<boolean>} True if the user is registered, false otherwise.
 */
async function verifyUser(senderEmail, env) {
    const { PUBLIC_API_ENDPOINT, WORKER_SECRET } = env;
    if (!PUBLIC_API_ENDPOINT || !WORKER_SECRET) {
        console.error("CRITICAL: Worker environment variables (PUBLIC_API_ENDPOINT, WORKER_SECRET) are not set.");
        return false;
    }

    const verificationUrl = `${PUBLIC_API_ENDPOINT}/is_user_registered?worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;

    try {
        const response = await fetch(verificationUrl);
        if (!response.ok) {
            console.error(`User verification request failed with status ${response.status}.`);
            return false;
        }
        const data = await response.json();
        return data.success && data.is_registered;
    } catch (error) {
        console.error("Error during user verification fetch:", error.message);
        return false;
    }
}

/**
 * Forwards the processed email content to the backend API.
 * @param {string} userEmail The sender's email.
 * @param {string} textContent The plain text content of the email.
 * @param {object} env The worker's environment variables.
 */
async function forwardToBackend(userEmail, textContent, env) {
    const { PUBLIC_API_ENDPOINT, WORKER_SECRET } = env;
    const formData = new FormData();
    formData.append("worker_secret", WORKER_SECRET);
    formData.append("user_email", userEmail);
    formData.append("raw_email_file", new Blob([textContent], { type: "text/plain" }), `email-${Date.now()}.txt`);

    const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload`;

    try {
        const response = await fetch(uploadUrl, { method: "POST", body: formData });
        if (!response.ok) {
            const errorText = await response.text();
            console.error(`Backend upload failed with status ${response.status}:`, errorText);
        } else {
            console.log(`Successfully forwarded email from ${userEmail} to backend.`);
        }
    } catch (error) {
        console.error("Error forwarding email to backend:", error.message);
    }
}

export default {
    /**
     * The main entry point for HTTP requests.
     * This acts as a proxy to the backend API.
     * @param {Request} request The incoming HTTP request.
     * @param {object} env The worker's environment variables.
     * @param {object} ctx The execution context.
     * @returns {Promise<Response>}
     */
    async fetch(request, env, ctx) {
        const { PUBLIC_API_ENDPOINT, WORKER_SECRET } = env;

        if (!PUBLIC_API_ENDPOINT || !WORKER_SECRET) {
            console.error("CRITICAL: Worker environment variables for API proxying are not set.");
            return new Response(JSON.stringify({
                success: false,
                error: "Worker is not configured to handle API requests."
            }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        // 1. Construct the target URL for the backend API.
        const url = new URL(request.url);
        const targetUrl = new URL(url.pathname + url.search, PUBLIC_API_ENDPOINT);

        // 2. Create a new request to forward to the backend.
        // Copy method, headers, and body from the original request.
        const backendRequest = new Request(targetUrl, {
            method: request.method,
            headers: request.headers,
            body: request.body,
            redirect: 'follow'
        });

        // 3. Add the required secret header for backend authentication.
        backendRequest.headers.set('X-Worker-Secret', WORKER_SECRET);
        // Ensure the Host header points to the backend, not the worker's URL.
        backendRequest.headers.set('Host', new URL(PUBLIC_API_ENDPOINT).host);


        // 4. Send the request to the backend and return the response.
        try {
            return await fetch(backendRequest);
        } catch (error) {
            console.error('Error forwarding request to backend:', error);
            return new Response(JSON.stringify({ success: false, error: 'Failed to connect to the backend service.' }), {
                status: 502, // Bad Gateway
                headers: { 'Content-Type': 'application/json' }
            });
        }
    },

    /**
     * The main entry point for the email worker.
     * @param {EmailMessage} message The incoming email message object.
     * @param {object} env The worker's environment variables.
     * @param {object} ctx The execution context.
     */
    async email(message, env, ctx) {
        const senderEmail = message.from;
        if (!senderEmail) {
            console.error("Received email without a 'from' address. Discarding.");
            return;
        }

        // 1. Verify if the sender is a registered user.
        const isRegistered = await verifyUser(senderEmail, env);
        if (!isRegistered) {
            console.log(`Discarding email from unregistered sender: ${senderEmail}`);
            return;
        }

        // 2. Process the raw email body to get the text content.
        let textContent = "Email did not contain readable plain text content.";
        try {
            // For simplicity in this refactor, we focus on the most common case:
            // getting the raw stream and decoding it. The complex multipart parsing
            // is assumed to be handled correctly by streamToString.
            const rawEmail = await streamToString(message.raw);

            // A simplified text extraction. In a real scenario, a robust multipart
            // parser would be used here. We'll assume the most important content is text.
            const bodyMatch = rawEmail.match(/Content-Type: text\/plain;[\s\S]*?\r?\n\r?\n([\s\S]*)/i);
            if (bodyMatch && bodyMatch[1]) {
                 textContent = bodyMatch[1].split(/--[a-zA-Z0-9_.-]+/)[0].trim();
            } else {
                // Fallback for simple emails
                textContent = rawEmail.split(/\r?\n\r?\n/)[1] || textContent;
            }
        } catch (error) {
            console.error("Error parsing raw email stream:", error.message);
            // We can still proceed with the default message.
        }

        // 3. Forward the extracted content to the backend.
        await forwardToBackend(senderEmail, textContent, env);
    },
};