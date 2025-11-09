// File: frontend/pages/EmailsListPage.jsx (Was EmailsPage.jsx)

import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { apiService } from '../api';

const StatusBadge = ({ status }) => {
    const statusStyles = {
        pending: { backgroundColor: '#ffc107', color: '#333' },
        processed: { backgroundColor: '#28a745', color: 'white' },
        failed: { backgroundColor: '#dc3545', color: 'white' },
    };
    const style = { padding: '0.25rem 0.5rem', borderRadius: '12px', fontSize: '0.8rem', fontWeight: 'bold', ...statusStyles[status] };
    return <span style={style}>{status.toUpperCase()}</span>;
};

function EmailsListPage() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    apiService.getEmails()
      .then(response => {
        if (response.status === 'success') setEmails(response.data);
        else setError(response.message);
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  const renderContent = () => {
    if (loading) return <p>正在加载邮件列表...</p>;
    if (error) return <p style={{ color: 'red' }}>错误: {error}</p>;
    if (emails.length === 0) return <p>没有找到任何邮件。</p>;

    return (
      <table>
        <thead>
          <tr>
            <th>邮件 ID</th>
            <th>处理状态</th>
            <th>接收时间</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          {emails.map(email => (
            <tr key={email.id}>
              <td>#{email.id}</td>
              <td><StatusBadge status={email.status} /></td>
              <td>{new Date(email.received_at).toLocaleString()}</td>
              <td>
                <Link to={`/emails/${email.id}`} className="button-link">
                  查看原文与结算
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  };

  return (
    <div className="card">
      <h2>邮件列表</h2>
      {renderContent()}
    </div>
  );
}

export default EmailsListPage;
