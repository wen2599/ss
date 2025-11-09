// File: frontend/src/pages/EmailsPage.jsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom'; // <-- 引入 Link
import { apiService } from '../api';

const StatusBadge = ({ status }) => {
  const statusClasses = {
    pending: 'status-pending',
    processed: 'status-processed',
    failed: 'status-failed',
  };
  return <span className={`status-badge ${statusClasses[status] || ''}`}>{status}</span>;
};

function EmailsPage() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [expandedEmailId, setExpandedEmailId] = useState(null);
  const [emailContent, setEmailContent] = useState('');
  const [contentLoading, setContentLoading] = useState(false);

  useEffect(() => {
    apiService.getEmails()
      .then(res => setEmails(res.data))
      .catch(setError)
      .finally(() => setLoading(false));
  }, []);

  const toggleEmailContent = (emailId) => {
    if (expandedEmailId === emailId) {
      setExpandedEmailId(null);
    } else {
      setExpandedEmailId(emailId);
      setContentLoading(true);
      apiService.getEmailContent(emailId)
        .then(res => setEmailContent(res.data.content))
        .catch(err => setEmailContent('Failed to load content.'))
        .finally(() => setContentLoading(false));
    }
  };

  const renderContent = () => {
    if (loading) return <p>Loading emails...</p>;
    if (error) return <p>Error: {error.message}</p>;
    if (emails.length === 0) return <p>No emails found.</p>;

    return (
      <table className="table-fixed-layout">
        <thead>
          <tr>
            <th>ID</th>
            <th>接收时间</th>
            <th>状态</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          {emails.map(email => (
            <React.Fragment key={email.id}>
              <tr>
                <td>{email.id}</td>
                <td>{new Date(email.received_at).toLocaleString()}</td>
                <td><StatusBadge status={email.status} /></td>
                <td>
                  <Link to={`/emails/${email.id}`} className="button-link">
                    查看详情与结算
                  </Link>
                </td>
              </tr>
              {expandedEmailId === email.id && (
                <tr>
                  <td colSpan="4">
                    <div className="email-content-container">
                      {contentLoading ? <p>Loading content...</p> : <pre>{emailContent}</pre>}
                    </div>
                  </td>
                </tr>
              )}
            </React.Fragment>
          ))}
        </tbody>
      </table>
    );
  };

  return (
    <div className="card">
      <h2>收到的邮件</h2>
      {renderContent()}
    </div>
  );
}

export default EmailsPage;
