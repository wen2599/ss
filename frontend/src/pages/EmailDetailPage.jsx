// File: frontend/pages/EmailDetailPage.jsx (Read-Only Version)
import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';

function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [emailContent, setEmailContent] = useState('');

  useEffect(() => {
    // 这个页面只需要邮件原文，调用 get_email_details 会有点浪费
    // 但为了重用 API, 我们暂时还用它
    // 更好的做法是后端提供一个只返回 content 的接口
    apiService.getEmailDetails(emailId)
      .then(res => {
        if (res.status === 'success') {
          setEmailContent(res.data.email_content);
        } else {
          setError(res.message);
        }
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, [emailId]);

  if (loading) return <p>正在加载邮件原文...</p>;
  if (error) return <p>错误: {error.message}</p>;

  return (
    <div className="card">
      <h2>邮件原文 (ID: {emailId})</h2>
      <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-all', backgroundColor: '#f9f9f9', padding: '1rem', borderRadius: '4px' }}>
        {emailContent}
      </pre>
    </div>
  );
}

export default EmailDetailPage;
