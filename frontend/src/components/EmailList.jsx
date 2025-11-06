import React, { useState, useEffect } from 'react';
import { getEmails } from '../services/api';

const EmailList = () => {
  const [emails, setEmails] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchEmails = async () => {
      try {
        const data = await getEmails();
        setEmails(data);
      } catch (err) {
        setError('无法加载邮件: ' + err.message);
      } finally {
        setIsLoading(false);
      }
    };

    fetchEmails();
  }, []);

  if (isLoading) return <p>正在加载邮件...</p>;
  if (error) return <p className="error-message">{error}</p>;

  return (
    <div>
      <h2>邮件原文</h2>
      {emails.length === 0 ? (
        <p>没有找到任何邮件。</p>
      ) : (
        <ul className="content-list">
          {emails.map((email) => (
            <li key={email.id} className="content-item">
              <p><strong>收到时间:</strong> {new Date(email.received_at).toLocaleString()}</p>
              <p><strong>状态:</strong> {email.status}</p>
              <details>
                <summary>查看邮件原文</summary>
                <pre>{email.raw_content}</pre>
              </details>
              {/* Add a button here to trigger AI processing in the future */}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

export default EmailList;
