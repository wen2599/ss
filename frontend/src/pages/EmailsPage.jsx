// src/pages/EmailsPage.jsx
import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

function EmailsPage() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiService.getEmails()
      .then(data => setEmails(data.data))
      .catch(err => console.error(err))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <p>正在加载邮件...</p>;

  return (
    <div className="card">
      <h2>邮件原文列表</h2>
      <ul>
        {emails.map(email => (
          <li key={email.id}>
            <strong>{email.subject}</strong> - <em>收到于: {new Date(email.received_at).toLocaleString()}</em>
          </li>
        ))}
      </ul>
    </div>
  );
}
export default EmailsPage;