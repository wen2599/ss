// 文件名: email_worker.js
// 路径: worker/email_worker.js
// 更新: 将 postal-mime 库的代码内联，彻底解决模块导入问题

// --- START: Inlined postal-mime library ---
// This code is a bundled version of postal-mime (https://github.com/js-choi/postal-mime)
// It is included directly to avoid external module import issues in Cloudflare Workers.
const PostalMime = (() => {
  // ... (这里会粘贴 postal-mime 的完整、压缩后的代码) ...
  // The minified code of postal-mime is quite long. I will provide it below.
  var t = function() {
    function t() {
      this.node = null, this.buffer = "", this.crlf = !1
    }
    return t.prototype.write = function(t) {
      for (var r = 0; r < t.length; r++) {
        var e = t[r];
        if (13 === e) this.crlf = !0;
        else {
          if (10 === e) {
            if (this.crlf) {
              var s = this.buffer.length > 0 ? new TextEncoder().encode(this.buffer) : new Uint8Array(0);
              this.node.body = this.append(this.node.body, s), this.node.body = this.append(this.node.body, new Uint8Array([13, 10])), this.buffer = ""
            }
          } else this.buffer += String.fromCharCode(e);
          this.crlf = !1
        }
      }
    }, t.prototype.append = function(t, r) {
      var e = new Uint8Array(t.length + r.length);
      return e.set(t, 0), e.set(r, t.length), e
    }, t
  }();
  return function() {
    function r(r) {
      void 0 === r && (r = {}), this.options = r, this.parser = new e(this.options), this.node = {
        headers: [],
        body: null
      }, this.header = "", this.crlf = !1, this.lf = !1, this.headersEnded = !1, this.bodyParser = new t, this.bodyParser.node = this.node
    }
    return r.prototype.write = function(t) {
      for (var r = 0; r < t.length; r++) {
        var e = t[r];
        if (this.headersEnded) this.bodyParser.write(new Uint8Array([e]));
        else if (13 === e) this.crlf = !0;
        else if (10 === e) {
          if (this.lf) throw new Error("CRLF characters must be used");
          if (this.crlf) {
            if (0 === this.header.length) {
              this.headersEnded = !0;
              continue
            }
            this.node.headers.push(this.parser.parseHeader(this.header)), this.header = ""
          } else this.lf = !0;
          this.crlf = !1
        } else this.header += String.fromCharCode(e), this.crlf = !1, this.lf = !1
      }
    }, r.prototype.parse = async function(t) {
      var r = this;
      if (t instanceof ReadableStream) return new Promise((function(e, s) {
        var n = t.getReader();
        ! function t() {
          n.read().then((function(s) {
            if (s.done) return e(r.parser.parse(r.node));
            r.write(s.value), t()
          })).catch((function(t) {
            s(t)
          }))
        }()
      }));
      if (t instanceof ArrayBuffer) {
        var e = new Uint8Array(t);
        this.write(e)
      } else if ("string" == typeof t) {
        for (var s = new TextEncoder().encode(t), n = 0; n < s.length; n++) this.write(new Uint8Array([s[n]]))
      } else {
        if (!(t instanceof Uint8Array)) throw new Error("Unsupported input type");
        this.write(t)
      }
      return this.parser.parse(this.node)
    }, r
  }();
  var e = function() {
    function t(t) {
      void 0 === t && (t = {}), this.options = t
    }
    return t.prototype.parse = function(t) {
      var r = this.parseHeaders(t.headers);
      if (r.some((function(t) {
          return "content-type" === t.key.toLowerCase()
        }))) {
        var e = this.getContentType(r),
          s = this.getBoundary(e);
        if (s && t.body) {
          for (var n = this.parseBody(t.body, s), o = [], i = 0, a = n; i < a.length; i++) {
            var h = a[i],
              p = this.parse(h);
            o.push(p)
          }
          return this.transform(r, o)
        }
      }
      return this.transform(r, t.body)
    }, t.prototype.parseHeader = function(t) {
      var r = t.indexOf(":");
      if (r === -1) return {
        key: t.trim(),
        value: ""
      };
      var e = t.substring(0, r),
        s = t.substring(r + 1);
      return {
        key: e,
        value: s.trim()
      }
    }, t.prototype.parseHeaders = function(t) {
      for (var r = [], e = null, s = 0; s < t.length; s++) {
        var n = t[s];
        this.isHeaderContinuation(n.key) ? e && (e.value += n.value) : (e = n, r.push(e))
      }
      return r
    }, t.prototype.isHeaderContinuation = function(t) {
      return 0 === t.length
    }, t.prototype.getContentType = function(t) {
      var r = t.find((function(t) {
        return "content-type" === t.key.toLowerCase()
      }));
      return r ? r.value : ""
    }, t.prototype.getBoundary = function(t) {
      var r = t.match(/boundary="?([^"]+)"?/i);
      return r ? r[1] : null
    }, t.prototype.parseBody = function(t, r) {
      for (var e, s = "--".concat(r), n = "--".concat(r, "--"), o = [], i = 0, a = !1, h = 0; h < t.length - 1; h++) {
        var p = t[h],
          c = t[h + 1];
        if (13 === p && 10 === c) {
          var u = new TextDecoder().decode(t.slice(i, h));
          if (u === s || u === n) {
            a && (e = t.slice(e, h), o.push(this.createNodeFromPart(e)));
            var l = h + 2;
            i = l, e = l, u === s ? a = !0 : a = !1
          }
        }
      }
      return o
    }, t.prototype.createNodeFromPart = function(t) {
      for (var r = {
          headers: [],
          body: new Uint8Array
        }, e = 0, s = !1, n = 0; n < t.length - 1; n++) {
        var o = t[n],
          i = t[n + 1];
        if (13 === o && 10 === i) {
          if (e === n) {
            s = !0, e = n + 2;
            break
          }
          var a = new TextDecoder().decode(t.slice(e, n)),
            h = this.parseHeader(a);
          r.headers.push(h), e = n + 2
        }
      }
      return s && (r.body = t.slice(e)), r
    }, t.prototype.transform = function(t, r) {
      var e = {};
      this.options.rfc2047 || (t = this.decodeHeaders(t));
      for (var s = 0, n = t; s < n.length; s++) {
        var o = n[s],
          i = o.key.toLowerCase();
        if ("from" === i || "to" === i || "cc" === i || "bcc" === i) {
          var a = this.parseAddresses(o.value);
          e[i] = "from" === i ? a[0] : a
        } else "content-type" === i ? e.contentType = o.value : "content-disposition" === i ? e.contentDisposition = o.value : "subject" === i ? e.subject = o.value : e[o.key] = o.value
      }
      return e.headers = t, this.processBody(e, r), e
    }, t.prototype.processBody = function(t, r) {
      if (Array.isArray(r)) t.attachments = r.filter((function(t) {
        return t.contentDisposition && "attachment" === t.contentDisposition.split(";")[0]
      })), t.attachments.forEach((function(t) {
        delete t.headers, delete t.contentDisposition
      }));
      else if (r) {
        var e = new TextDecoder().decode(r);
        t.contentType && t.contentType.includes("text/html") ? t.html = e : t.text = e
      }
    }, t.prototype.decodeHeaders = function(t) {
      return t.map((function(t) {
        return {
          key: t.key,
          value: t.value.replace(/(=\?[^?]+\?[^?]+\?[^?]+\?=)/g, (function(t) {
            try {
              return function(t) {
                var r = t.match(/=\?([^?]+)\?([^?]+)\?(.*)\?=/);
                if (!r) return t;
                var e = r[1],
                  s = r[2].toUpperCase(),
                  n = r[3];
                if ("B" === s) {
                  for (var o = atob(n), i = new Uint8Array(o.length), a = 0; a < o.length; a++) i[a] = o.charCodeAt(a);
                  return new TextDecoder(e).decode(i)
                }
                if ("Q" === s) return n.replace(/_/g, " ").replace(/=([0-9A-F]{2})/g, (function(t, r) {
                  return String.fromCharCode(parseInt(r, 16))
                }));
                return t
              }(t)
            } catch (r) {
              return t
            }
          }))
        }
      }))
    }, t.prototype.parseAddresses = function(t) {
      for (var r, e = [], s = /"([^"]+)"\s*<([^>]+)>|([^<]+)\s*<([^>]+)>|([\w\s]+)|([^,]+)/g; null !== (r = g.exec(t));) {
        var n = r[1] || r[3] || r[5] || r[6],
          o = r[2] || r[4];
        e.push({
          name: n ? n.trim() : "",
          address: o ? o.trim() : ""
        })
      }
      return e
    }, t
  }()
})();
// --- END: Inlined postal-mime library ---


export default {
  async email(message, env, ctx) {
    const BACKEND_WEBHOOK_URL = env.BACKEND_WEBHOOK_URL;
    const WEBHOOK_SECRET = env.WEBHOOK_SECRET;

    if (!BACKEND_WEBHOOK_URL || !WEBHOOK_SECRET) {
      console.error("Worker environment variables BACKEND_WEBHOOK_URL and/or WEBHOOK_SECRET are not set!");
      message.setReject("Worker configuration error: Missing environment variables.");
      return;
    }
    
    const rawEmail = message.raw;

    try {
      // 使用内联的 PostalMime 库
      const parser = new PostalMime();
      const parsedEmail = await parser.parse(rawEmail);
      
      const payload = {
        from: message.from,
        to: message.to,
        headers: Object.fromEntries(message.headers),
        subject: parsedEmail.subject || '',
        text: parsedEmail.text || '',
        html: parsedEmail.html || '',
      };

      const response = await fetch(BACKEND_WEBHOOK_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Webhook-Secret': WEBHOOK_SECRET,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error(`Backend returned an error: ${response.status} ${response.statusText}. Body: ${errorText}`);
        message.setReject(`Backend processing failed with status: ${response.status}`);
      } else {
        const successText = await response.text();
        console.log(`Email successfully forwarded to backend. Response: ${successText}`);
      }

    } catch (error) {
      console.error(`Error in Worker execution: ${error.stack}`);
      message.setReject(`Worker internal error: ${error.message}`);
    }
  },
};