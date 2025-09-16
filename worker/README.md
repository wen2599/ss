# Cloudflare Worker Email Handler Deployment

This document provides instructions on how to deploy the `email_handler.js` worker to your Cloudflare account and configure it to process incoming emails.

## Prerequisites

1.  You have a Cloudflare account.
2.  You have a domain managed by Cloudflare (e.g., `wenge.cloudns.ch`).
3.  You have configured the MX records for your domain to point to Cloudflare for email receiving. You can find instructions on how to do this in your Cloudflare dashboard under "Email Routing".

## Deployment Steps

### 1. Create a New Worker

1.  Log in to your Cloudflare dashboard.
2.  In the left-hand menu, go to **Workers & Pages**.
3.  Click on **Create application**.
4.  Select the **Workers** tab and click **Create worker**.
5.  Give your worker a name (e.g., `email-chat-parser`) and click **Deploy**.

### 2. Configure the Worker

1.  After the worker is deployed, click on **Edit code**.
2.  You will see a default "Hello World" script. **Delete all the content** in the editor.
3.  Copy the entire content of the `email_handler.js` file from this repository.
4.  Paste the copied code into the Cloudflare editor.
5.  Click **Save and deploy**.

### 3. Configure Email Routing

1.  In your Cloudflare dashboard, go to **Email** > **Email Routing**.
2.  Go to the **Routes** tab.
3.  Click on **Create address**.
4.  In the **Custom address** field, enter the email address you want to use to receive chat logs (e.g., `chat` if your domain is `wenge.cloudns.ch`, which will create `chat@wenge.cloudns.ch`).
5.  In the **Action** dropdown, select **Send to a Worker**.
6.  In the **Worker** dropdown that appears, select the worker you created in Step 1 (e.g., `email-chat-parser`).
7.  Click **Save**.

## How It Works

Once you have completed these steps, any email sent to the custom address you configured will be processed by the worker. The worker will extract the text content of the email, treat it as a chat log file, and send it to your PHP backend for parsing and storage.

You should then be able to see the new log appear in the "已存记录" (Stored Logs) section of the web application.
