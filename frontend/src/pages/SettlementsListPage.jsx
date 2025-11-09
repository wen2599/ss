// File: frontend/src/pages/SettlementsListPage.jsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { apiService } from '../api';

// Reusable StatusBadge component
const statusStyles = {
    pending: { backgroundColor: '#ffc107', color: '#333' },
    processed: { backgroundColor: '#28a745', color: 'white' },
    failed: { backgroundColor: '#dc3545', color: 'white' },
};
const StatusBadge = ({ status }) => {
    const style = {
        padding: '0.25rem 0.5rem', borderRadius: '12px',
        fontSize: '0.8rem', fontWeight: 'bold', ...statusStyles[status],
    };
    return <span style={style}>{status.toUpperCase()}</span>;
};

function SettlementsListPage() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    // Future improvement: The backend could provide an endpoint to get only emails
    // that are relevant for settlement (e.g., status = 'processed').
    apiService.getEmails()
      .then(res => {
        if (res.data) {
          setEmails(res.data);
        }
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <p>正在加载待结算邮件列表...</p>;
  if (error) return <p>错误: {error}</p>;

  // Filter for emails that are most likely to have settlement data.
  const settlementCandidates = emails.filter(e => e.status === 'processed' || e.status === 'pending');

  return (
    <div className="card">
      <h2>选择邮件进行结算</h2>
      {settlementCandidates.length === 0 ? (
        <p>没有找到需要结算的邮件。</p>
      ) : (
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
            {settlementCandidates.map(email => (
              <tr key={email.id}>
                <td>#{email.id}</td>
                <td><StatusBadge status={email.status} /></td>
                <td>{new Date(email.received_at).toLocaleString()}</td>
                <td>
                  <Link to={`/settlements/${email.id}`} className="button-link">
                    进入结算工作台
                  </Link>
                </td>
              </tr>
            ))}  
          </tbody>
        </table>
      )}
    </div>
  );
}

export default SettlementsListPage;
