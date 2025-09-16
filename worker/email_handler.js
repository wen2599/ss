export default {
  async email(message, env, ctx) {
    // The backend API endpoint for uploading chat files
    const UPLOAD_API_URL = "https://wenge.cloudns.ch/backend/api/api.php";

    // Create a new FormData object to build the multipart/form-data request
    const formData = new FormData();

    // Get the raw email content as a string
    const rawEmail = await streamToString(message.raw);

    // For simplicity, we'll extract the text body.
    // A more robust solution would parse the email properly (e.g., using a library).
    // This simple regex finds the first plain text part of the email.
    const textBodyMatch = rawEmail.match(/Content-Type: text\/plain;[\s\S]*?\r\n\r\n([\s\S]*)/);

    let chatContent = "Email did not contain a recognizable text part.";
    if (textBodyMatch && textBodyMatch[1]) {
      chatContent = textBodyMatch[1];
    }

    // Create a Blob from the chat content to simulate a file
    const blob = new Blob([chatContent], { type: 'text/plain' });

    // Append the blob to the FormData object, just like a file input would
    // We'll use a dynamic filename.
    const filename = `email-${message.headers.get("message-id") || new Date().toISOString()}.txt`;
    formData.append('chat_file', blob, filename);

    try {
      // Send the POST request to the PHP backend
      const response = await fetch(UPLOAD_API_URL, {
        method: 'POST',
        body: formData,
      });

      // Check if the request was successful
      if (!response.ok) {
        const errorText = await response.text();
        console.error(`Backend error: ${response.status} ${response.statusText}`, errorText);
        // You could forward the error notification somewhere if needed
      } else {
        console.log(`Successfully forwarded email to backend. Filename: ${filename}`);
      }

    } catch (error) {
      console.error("Failed to fetch backend API:", error);
    }
  },
};

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
