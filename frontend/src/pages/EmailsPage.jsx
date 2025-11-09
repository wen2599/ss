// File: frontend/src/pages/EmailsPage.jsx

import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

// 状态标签组件，用于显示邮件处理状态
const StatusBadge = ({ status }) => {
    const statusStyles = {
        pending: { backgroundColor: '#ffc107', color: '#333' },
        processed: { backgroundColor: '#28a745', color: 'white' },
        failed: { backgroundColor: '#dc3545', color: 'white' },
    };
    const style = {
        padding: '0.25rem 0.5rem',
        borderRadius: '12px',
        fontSize: '0.8rem',
        fontWeight: 'bold',
        ...statusStyles[status],
    };
    return <span style={style}>{status.toUpperCase()}</span>;
};


function EmailsPage() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError(null);

    apiService.getEmails()
      .then(response => {
        if (response.status === 'success') {
          setEmails(response.data);
        } else {
          // 如果后端返回 { status: 'error', message: '...' }
          setError(response.message);
        }
      })
      .catch(err => {
        // 网络错误或 API 抛出的其他错误
        setError(err.message);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []); // 空依赖数组，只在组件首次挂载时执行

  const renderContent = () => {
    if (loading) {
      return <p>正在加载邮件列表...</p>;
    }
    if (error) {
      return <p className="message" style={{ color: 'red' }}>错误: {error}</p>;
    }
    if (emails.length === 0) {
      return <p>您还没有发送任何邮件。</p>;
    }
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
              <td><button className="link-button">查看详情</button></td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  };

  return (
    <div className="card">
      <h2>邮件原文列表</h2>
      {renderContent()}
    </div>
  );
}

export default EmailsPage;