// File: frontend/src/pages/EmailsPage.jsx

import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

const StatusBadge = ({ status }) => {
    // ... (组件代码保持不变)
    const statusStyles = {
        pending: { backgroundColor: '#ffc107', color: '#333' },
        processed: { backgroundColor: '#28a745', color: 'white' },
        failed: { backgroundColor: '#dc3545', color: 'white' },
    };
    const style = {
        padding: '0.25rem 0.5rem', borderRadius: '12px',
        fontSize: '0.8rem', fontWeight: 'bold', ...statusStyles[status],
    };
    return <span style={style}>{status.toUpperCase()}</span>;
};

// 新增：邮件详情弹窗组件
const EmailDetailModal = ({ emailContent, onClose }) => {
    if (!emailContent) return null;

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, width: '100%', height: '100%',
            backgroundColor: 'rgba(0, 0, 0, 0.5)', display: 'flex',
            justifyContent: 'center', alignItems: 'center', zIndex: 1000
        }}>
            <div style={{
                backgroundColor: 'white', padding: '2rem', borderRadius: '8px',
                width: '80%', maxWidth: '800px', height: '70%', overflowY: 'auto',
                position: 'relative'
            }}>
                <button onClick={onClose} style={{
                    position: 'absolute', top: '10px', right: '10px',
                    background: 'none', border: 'none', fontSize: '1.5rem', cursor: 'pointer'
                }}>
                    &times;
                </button>
                <h3>邮件原文 (ID: {emailContent.id})</h3>
                <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>
                    {emailContent.content}
                </pre>
            </div>
        </div>
    );
};


function EmailsPage() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  // 新增状态：用于弹窗
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);

  useEffect(() => {
    apiService.getEmails()
      .then(response => {
        if (response.status === 'success') setEmails(response.data);
        else setError(response.message);
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  // 点击“查看详情”按钮的处理函数
  const handleViewDetail = (emailId) => {
    setDetailLoading(true);
    setSelectedEmail(null); // 清空旧内容
    apiService.getEmailContent(emailId)
        .then(response => {
            if (response.status === 'success') {
                setSelectedEmail(response.data);
            } else {
                alert(`加载详情失败: ${response.message}`);
            }
        })
        .catch(err => alert(`错误: ${err.message}`))
        .finally(() => setDetailLoading(false));
  };
  
  const renderContent = () => {
    if (loading) return <p>正在加载邮件列表...</p>;
    if (error) return <p className="message" style={{ color: 'red' }}>错误: {error}</p>;
    if (emails.length === 0) return <p>您还没有发送任何邮件。</p>;

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
                <button 
                  className="link-button" 
                  onClick={() => handleViewDetail(email.id)}
                  disabled={detailLoading}
                >
                  {detailLoading ? '加载中...' : '查看详情'}
                </button>
              </td>
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
      
      {/* 弹窗组件 */}
      <EmailDetailModal 
        emailContent={selectedEmail} 
        onClose={() => setSelectedEmail(null)} 
      />
    </div>
  );
}

export default EmailsPage;