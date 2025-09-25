// worker/email_handler.js (Fixed: API 路径统一，确保后端能收到字段)

export default {
  async email(message, env, ctx) {
    // 注意：这里 PUBLIC_API_ENDPOINT 直接写成 pages 入口地址
    const PUBLIC_API_ENDPOINT = "https://ss.wenxiuxiu.eu.org";
    const WORKER_SECRET = "816429fb-1649-4e48-9288-7629893311a6";

    const senderEmail = message.from;
    if (!senderEmail) {
      console.error("Email received without a sender address.");
      return;
    }

    // 1. Verify user is registered before proceeding
    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/is_user_registered?action=is_user_registered&worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);
      if (!verificationResponse.ok) {
        console.error(`User verification request failed with status: ${verificationResponse.status}`);
        return;
      }
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) {
        console.log(`Email from unregistered user '${senderEmail}' was rejected by the backend.`);
        return;
      }
    } catch (error) {
      console.error("Failed to execute user verification fetch: " + error.message);
      return;
    }

    // 2. Forward the entire raw email as a file to the backend
    try {
      const rawEmailBlob = await message.raw.blob();
      if (rawEmailBlob.size === 0) {
        console.error("Email has an empty raw body.");
        return;
      }

      const formData = new FormData();
      formData.append("worker_secret", WORKER_SECRET);
      formData.append("user_email", senderEmail);
      formData.append("raw_email_file", rawEmailBlob, "email.eml");

      // 关键：接口地址必须带上 action 参数，走 PHP 路由
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/email_upload?action=email_upload`;
      const uploadResponse = await fetch(uploadUrl, {
        method: "POST",
        body: formData,
      });

      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error(`Backend upload returned an error: ${uploadResponse.status} ${uploadResponse.statusText}`, errorText);
      } else {
        console.log(`Successfully forwarded raw email from ${senderEmail} to the backend for parsing.`);
      }
    } catch (error) {
      console.error("Failed to forward the raw email to the backend: " + error.message);
    }
  },
};
