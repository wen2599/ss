import React from 'react';

const EmailList = ({ emails, selectedEmailId, onSelectEmail, loading, error }) => {
  if (loading) return <p className="loading">正在加载邮件列表...</p>;
  if (error) return <p className="error">{error}</p>;

  return (
    <div className="email-list">
      <h3>收件箱</h3>
      {emails.length === 0 ? (
        <p>没有邮件。</p>
      ) : (
        emails.map(email => (
          <div
            key={email.id}
            className={`email-item ${selectedEmailId === email.id ? 'selected' : ''}`}
            onClick={() => onSelectEmail(email.id)}
          >
            <strong>主题: {email.subject || '(无主题)'}</strong>
            <p>来自: {email.from_address}</p>
            <p>状态: {email.status}</p>
            <p style={{fontSize: '0.8em', color: '#aaa'}}>{new Date(email.created_at).toLocaleString()}</p>
          </div>
        ))
      )}
    </div>
  );
};

export default EmailList;