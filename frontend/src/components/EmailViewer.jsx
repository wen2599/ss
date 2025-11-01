import React, { useState, useEffect } from 'react';
import axios from 'axios';

// --- FIX: Change API URLs to relative paths for Cloudflare Worker proxy ---
const API_BASE_URL = '/api';
const EMAILS_API_URL = `${API_BASE_URL}/get_emails.php`;
const EMAIL_DETAIL_API_URL = `${API_BASE_URL}/get_email_details.php`;

function EmailViewer() {
  const [emails, setEmails] = useState([]);
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchEmails = async () => {
      const token = localStorage.getItem('token');
      if (!token) {
        setError('请先登录以查看邮件。');
        setLoading(false);
        return;
      }

      try {
        const response = await axios.get(EMAILS_API_URL, {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        });

        if (response.data && Array.isArray(response.data.data)) {
            setEmails(response.data.data);
        } else {
            console.warn("API did not return an array of emails:", response.data);
            setEmails([]);
        }

      } catch (err) {
        if (err.response && err.response.status === 401) {
          setError('您的会话已过期，请重新登录。');
        } else {
          setError('无法加载邮件列表，请稍后重试。');
        }
        console.error("Error fetching emails:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchEmails();
  }, []);

  const handleEmailSelect = async (id) => {
    const token = localStorage.getItem('token');
    if (!token) {
        setSelectedEmail({ error: '请先登录以查看邮件内容。' });
        return;
    }

    setSelectedEmail({ loading: true });
    try {
      const response = await axios.get(`${EMAIL_DETAIL_API_URL}?id=${id}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setSelectedEmail(response.data.data);
    } catch (err) {
      console.error("Error fetching email details:", err);
      if (err.response && err.response.status === 401) {
        setSelectedEmail({ error: '您的会话已过期，请重新登录。' });
      } else {
        setSelectedEmail({ error: '无法加载邮件内容。' });
      }
    }
  };

  if (loading) return <p>加载邮件列表中...</p>;

  return (
    <div className="email-viewer">
      <div className="email-list">
        <h2>收件箱</h2>
        {error ? (
          <p style={{ color: 'red', padding: '1rem' }}>{error}</p>
        ) : emails.length > 0 ? (
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
