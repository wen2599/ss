import { useState, useEffect } from 'react';

function EmailList() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchEmails = async () => {
      try {
        setLoading(true);
        // _worker.js 会将此请求代理到您的 PHP 后端
        const response = await fetch('/api/get_emails.php');
        
        if (!response.ok) {
          throw new Error(`HTTP 错误! 状态: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          setEmails(data.data);
        } else {
          throw new Error(data.message || '获取邮件列表失败');
        }

      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchEmails();
  }, []);

  return (
    <div>
      <h2>收件箱</h2>
      {loading && <p className="loading">正在加载中...</p>}
      {error && <p className="error">错误: {error}</p>}
      {!loading && !error && (
        <ul className="item-list">
          {emails.length > 0 ? (
            emails.map((email) => (
              <li key={email.id}>
                <span>
                  <strong>{email.sender}</strong>: {email.subject}
                </span>
                <span>{new Date(email.received_at).toLocaleString()}</span>
              </li>
            ))
          ) : (
            <p>收件箱是空的。</p>
          )}
        </ul>
      )}
    </div>
  );
}

export default EmailList;
