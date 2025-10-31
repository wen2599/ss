import React, { useState, useEffect } from 'react';
import axios from 'axios';

const EMAILS_API_URL = `${import.meta.env.VITE_API_BASE_URL}/get_emails.php`;
const EMAIL_DETAIL_API_URL = `${import.meta.env.VITE_API_BASE_URL}/get_email_details.php`;

function EmailViewer() {
  const [emails, setEmails] = useState([]);
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchEmails = async () => {
      try {
        const response = await axios.get(EMAILS_API_URL);
        if (Array.isArray(response.data)) {
          setEmails(response.data);
        } else {
          setEmails([]);
        }
      } catch (err) {
        setError('无法加载邮件列表，请检查后端 API 是否正常。');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchEmails();
  }, []);

  const handleEmailSelect = async (id) => {
    setSelectedEmail({ loading: true }); // 显示加载状态
    try {
      const response = await axios.get(`${EMAIL_DETAIL_API_URL}?id=${id}`);
      setSelectedEmail(response.data);
    } catch (err) {
      console.error(err);
      setSelectedEmail({ error: '无法加载邮件内容。' });
    }
  };

  if (loading) return <p>加载邮件列表中...</p>;
  if (error) return <p style={{ color: 'red' }}>{error}</p>;

  return (
    <div className="email-viewer">
      <div className="email-list">
        <h2>收件箱</h2>
        {emails.length > 0 ? (
          <ul>
            {emails.map((email) => (
              <li key={email.id} onClick={() => handleEmailSelect(email.id)}>
                <div className="from">{email.from_address}</div>
                <div className="subject">{email.subject || '(无主题)'}</div>
                <div className="date">{new Date(email.received_at).toLocaleString()}</div>
              </li>
            ))}
          </ul>
        ) : (
          <p style={{padding: '1rem'}}>收件箱是空的。</p>
        )}
      </div>
      <div className="email-detail">
        {!selectedEmail ? (
          <p>请在左侧选择一封邮件查看。</p>
        ) : selectedEmail.loading ? (
          <p>加载邮件内容...</p>
        ) : selectedEmail.error ? (
          <p style={{ color: 'red' }}>{selectedEmail.error}</p>
        ) : (
          <div>
            <h3>{selectedEmail.subject || '(无主题)'}</h3>
            <p><strong>发件人:</strong> {selectedEmail.from_address}</p>
            <p><strong>收件时间:</strong> {new Date(selectedEmail.received_at).toLocaleString()}</p>
            <hr />
            {selectedEmail.body_html ? (
              <div dangerouslySetInnerHTML={{ __html: selectedEmail.body_html }} />
            ) : (
              <pre>{selectedEmail.body_text}</pre>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

export default EmailViewer;