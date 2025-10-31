/**
 * File: email_worker.js
 * Description: Cloudflare Worker to receive emails, parse them, and securely forward them to a PHP backend.
 * This worker is designed to be robust, with retry logic and structured logging.
 * Version: 2.7 - Firewall diagnostic build. Sends a minimal, hardcoded payload to the backend.
 */

// We are using a bundled version of postal-mime to avoid external dependencies.
// Source: https://github.com/js-choi/postal-mime
const PostalMime=function(){var t=function(){function t(){this.node=null,this.buffer="",this.crlf=!1}return t.prototype.write=function(t){for(var r=0;r<t.length;r++){var e=t[r];if(13===e)this.crlf=!0;else{if(10===e){if(this.crlf){var s=this.buffer.length>0?new TextEncoder().encode(this.buffer):new Uint8Array(0);
this.node.body=this.append(this.node.body,s),this.node.body=this.append(this.node.body,new Uint8Array([13,10])),this.buffer=""}}else this.buffer+=String.fromCharCode(e);
this.crlf=!1}}},t.prototype.append=function(t,r){var e=new Uint8Array(t.length+r.length);return e.set(t,0),e.set(r,t.length),e},t}();var r=function(){function t(t){void 0===t&&(t={}),this.options=t}return t.prototype.parse=function(t){var r=this.parseHeaders(t.headers);if(r.some((function(t){return"content-type"===t.key.toLowerCase()}))){var e=this.getContentType(r),s=this.getBoundary(e);if(s&&t.body){for(var n=this.parseBody(t.body,s),o=[],i=0,a=n;i<a.length;i++){var h=a[i],p=this.parse(h);
o.push(p)}return this.transform(r,o)}}return this.transform(r,t.body)},t.prototype.parseHeader=function(t){var r=t.indexOf(":");if(r===-1)return{key:t.trim(),value:""};var e=t.substring(0,r),s=t.substring(r+1);return{key:e,value:s.trim()}},t.prototype.parseHeaders=function(t){for(var r=[],e=null,s=0;s<t.length;s++){var n=t[s];
this.isHeaderContinuation(n.key)?e&&(e.value+=n.value):(e=n,r.push(e))}return r},t.prototype.isHeaderContinuation=function(t){return 0===t.length},t.prototype.getContentType=function(t){var r=t.find((function(t){return"content-type"===t.key.toLowerCase()}));return r?r.value:""},t.prototype.getBoundary=function(t){var r=t.match(/boundary="?([^\"]+)"?/i);return r?r[1]:null},t.prototype.parseBody=function(t,r){for(var e,s="--".concat(r),n="--".concat(r,"--"),o=[],i=0,a=!1,h=0;h<t.length-1;h++){var p=t[h],c=t[h+1];
if(13===p&&10===c){var u=new TextDecoder().decode(t.slice(i,h));if(u===s||u===n){a&&(e=t.slice(e,h),o.push(this.createNodeFromPart(e)));var l=h+2;
i=l,e=l,u===s?a=!0:a=!1}}}return o},t.prototype.createNodeFromPart=function(t){for(var r={headers:[],body:new Uint8Array},e=0,s=!1,n=0;n<t.length-1;n++){var o=t[n],i=t[n+1];if(13===o&&10===i){if(e===n){s=!0,e=n+2;break}var a=new TextDecoder().decode(t.slice(e,n)),h=this.parseHeader(a);
r.headers.push(h),e=n+2}}return s&&(r.body=t.slice(e)),r},t.prototype.transform=function(t,r){var e={};
this.options.rfc2047||(t=this.decodeHeaders(t));for(var s=0,n=t;s<n.length;s++){var o=n[s],i=o.key.toLowerCase();if("from"===i||"to"===i||"cc"===i||"bcc"===i){var a=this.parseAddresses(o.value);
e[i]="from"===i?a[0]:a}else"content-type"===i?e.contentType=o.value:"content-disposition"===i?e.contentDisposition=o.value:"subject"===i?e.subject=o.value:e[o.key]=o.value}return e.headers=t,this.processBody(e,r),e},t.prototype.processBody=function(t,r){if(Array.isArray(r))t.attachments=r.filter((function(t){return t.contentDisposition&&"attachment"===t.contentDisposition.split(";")[0]})),t.attachments.forEach((function(t){delete t.headers,delete t.contentDisposition}));
else if(r){var e=new TextDecoder().decode(r);
t.contentType&&t.contentType.includes("text/html")?t.html=e:t.text=e}},t.prototype.decodeHeaders=function(t){return t.map((function(t){return{key:t.key,value:t.value.replace(/(=\?[^?]+\?[^?]+\?[^?]+\?=)/g,(function(t){try{return function(t){var r=t.match(/=\?([^?]+)\?([^?]+)\?(.*)\?=/);if(!r)return t;var e=r[1],s=r[2].toUpperCase(),n=r[3];
if("B"===s){for(var o=atob(n),i=new Uint8Array(o.length),a=0;a<o.length;a++)i[a]=o.charCodeAt(a);return new TextDecoder(e).decode(i)}if("Q"===s)return n.replace(/_/g," ").replace(/=([0-9A-F]{2})/g,(function(t,r){return String.fromCharCode(parseInt(r,16))}));
return t}(t)}catch(r){return t}}))}}))},t.prototype.parseAddresses=function(t){for(var r,e=[],s=/\"([^\"]+)\"\s*<([^>]+)>|([^<]+)\s*<([^>]+)>|([\w\s]+)|([^,]+)/g;null!==(r=s.exec(t));){var n=r[1]||r[3]||r[5]||r[6],o=r[2]||r[4];
e.push({name:n?n.trim():"",address:o?o.trim():""})}return e},t}();var e=function(){function e(opts){void 0===opts&&(opts={}),this.options=opts,this.parser=new r(this.options),this.node={headers:[],body:new Uint8Array(0)},this.header="",this.crlf=!1,this.lf=!1,this.headersEnded=!1,this.bodyParser=new t,this.bodyParser.node=this.node}return e.prototype.write=function(t){for(var r=0;r<t.length;r++){var e=t[r];
if(this.headersEnded)this.bodyParser.write(new Uint8Array([e]));
else if(13===e)this.crlf=!0;
else if(10===e){if(this.lf)throw new Error("CRLF characters must be used");if(this.crlf){if(0===this.header.length){this.headersEnded=!0;continue}this.node.headers.push(this.parser.parseHeader(this.header)),this.header=""}else this.lf=!0;
this.crlf=!1}else this.header+=String.fromCharCode(e),this.crlf=!1,this.lf=!1}},e.prototype.parse=async function(t){var r=this;if(t instanceof ReadableStream)return new Promise((function(e,s){var n=t.getReader();!function t(){n.read().then((function(s){if(s.done)return e(r.parser.parse(r.node));
r.write(s.value),t()})).catch((function(t){s(t)}))}()}));if(t instanceof ArrayBuffer){var e=new Uint8Array(t);
this.write(e)}else if("string"==typeof t){for(var s=new TextEncoder().encode(t),n=0;n<s.length;n++)this.write(new Uint8Array([s[n]]))}else{if(!(t instanceof Uint8Array))throw new Error("Unsupported input type");
this.write(t)}return this.parser.parse(r.node)},e}();return e}();

/**
 * A helper function for structured JSON logging.
 * @param {string} level - Log level ('INFO', 'WARN', 'ERROR').
 * @param {string} message - The primary log message.
 * @param {object} data - Additional contextual data.
 */
function log(level, message, data = {}) {
  console.log(JSON.stringify({
    timestamp: new Date().toISOString(),
    level,
    message,
    ...data,
  }));
}

/**
 * Reads a ReadableStream and returns its content as a string.
 * @param {ReadableStream} stream - The stream to read.
 * @returns {Promise<string>} The content of the stream.
 */
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


/**
 * Forwards the parsed email payload to the backend API with a retry mechanism.
 * @param {object} payload - The JSON payload to send to the backend.
 * @param {string} url - The target backend URL.
 * @param {string} secret - The secret key to authenticate with the backend.
 * @param {string} messageId - The email's message-id for logging.
 * @returns {Promise<Response>} The final response from the backend.
 */
async function forwardToBackend(payload, url, secret, messageId) {
  const MAX_RETRIES = 3;
  const INITIAL_DELAY_MS = 1000; 

  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    log('INFO', `Forwarding email to backend`, { messageId, attempt, maxAttempts: MAX_RETRIES });

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WORKER-SECRET': secret, 
        },
        body: JSON.stringify(payload),
      });

      if (response.ok) {
        log('INFO', 'Backend acknowledged the email successfully.', { messageId, status: response.status });
        return response; 
      }

      const errorBody = await response.text();
      log('WARN', 'Backend returned a non-ok response.', { 
        messageId, 
        attempt, 
        status: response.status, 
        responseBody: errorBody 
      });

    } catch (error) {
      log('ERROR', 'Network or fetch error while forwarding to backend.', { 
        messageId, 
        attempt, 
        errorMessage: error.message,
      });
    }

    if (attempt < MAX_RETRIES) {
      const delay = INITIAL_DELAY_MS * Math.pow(2, attempt - 1);
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }

  throw new Error(`Failed to forward email after ${MAX_RETRIES} attempts.`);
}

export default {
  /**
   * The main entry point for the Cloudflare Email Worker.
   * @param {EmailMessage} message - The incoming email message object.
   * @param {object} env - Environment variables set in the Cloudflare dashboard.
   * @param {object} ctx - The execution context.
   */
  async email(message, env, ctx) {
    const { BACKEND_URL, WORKER_SECRET } = env;
    const messageId = message.headers.get('message-id') || `no-id-${Date.now()}`;

    if (!BACKEND_URL || !WORKER_SECRET) {
      log('ERROR', 'Worker environment variables (BACKEND_URL or WORKER_SECRET) are not set!', { messageId });
      message.setReject('Worker configuration error. Please contact the administrator.');
      return;
    }
    
    const fullApiUrl = `${BACKEND_URL.replace(/\/$/, '')}/receive_email.php`;

    try {
      // For this diagnostic build, we are ignoring the actual email content and sending a fixed payload.

      // 1. Construct the minimal, hardcoded payload for the firewall test.
      const payload = {
        message_id: `test-id-${Date.now()}`,
        from: "firewall-test@example.com",
        to: message.to,
        subject: "Firewall Diagnostic Test",
        text: "This is a test to see if the hosting provider\'s firewall is blocking the request.",
        html: null,
        raw_content: "Minimal content.",
      };

      // 2. Forward the minimal payload to the backend.
      await forwardToBackend(payload, fullApiUrl, WORKER_SECRET, messageId);

      log('INFO', 'Firewall diagnostic test forwarded successfully.', { messageId });

    } catch (error) {
      log('ERROR', 'Critical error during worker execution for firewall test.', { 
        messageId, 
        errorMessage: error.message,
      });
      message.setReject(`Failed to process and forward the email due to a persistent backend issue.`);
    }
  },
};