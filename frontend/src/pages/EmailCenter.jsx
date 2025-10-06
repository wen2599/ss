import { useState, useEffect, useCallback } from 'react';
import EmailUpload from '../components/EmailUpload';
import './EmailCenter.css'; // 引入新的样式文件

const LoadingState = () => (
    <div className="state-container">
        <p>正在刷新邮件列表，请稍候...</p>
    </div>
);

const ErrorState = ({ error }) => (
    <div className="state-container">
        <p className="error">数据加载失败: {error}</p>
    </div>
);

const EmptyState = () => (
    <tr>
        <td colSpan="4" className="empty-state-cell">
            收件箱中没有找到账单邮件。您可以尝试上传 .eml 文件。
        </td>
    </tr>
);

function EmailCenter() {
  const [emails, setEmails] = useState([]);
  const [error, setError] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  const fetchEmails = useCallback(async () => {
    setIsLoading(true);
    setError(null); // Reset error state on new fetch
    try {
      const response = await fetch('/get_emails', { credentials: 'include' });
      if (!response.ok) throw new Error(`服务器响应错误 (状态: ${response.status})`);
      
      const data = await response.json();
      if (data.error) throw new Error(data.error);
      
      setEmails(data.emails || []);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchEmails();
  }, [fetchEmails]);

  return (
    <div className="card email-center-container">
      <EmailUpload onUploadSuccess={fetchEmails} />
      
      <div className="table-container">
        <h2>收件箱</h2>
        <div className="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>发件人</th>
                        <th>主题</th>
                        <th>账单金额</th>
                    </tr>
                </thead>
                <tbody>
                    {isLoading ? (
                        <tr><td colSpan="4"><LoadingState /></td></tr>
                    ) : error ? (
                        <tr><td colSpan="4"><ErrorState error={error} /></td></tr>
                    ) : emails.length > 0 ? (
                        emails.map((email, index) => (
                        <tr key={email.id || index}>
                            <td data-label="日期">{new Date(email.date).toLocaleString()}</td>
                            <td data-label="发件人">{email.from}</td>
                            <td data-label="主题">{email.subject}</td>
                            <td data-label="账单金额" className="amount">{email.amount ? `$${email.amount}` : 'N/A'}</td>
                        </tr>
                        ))
                    ) : (
                        <EmptyState />
                    )}
                </tbody>
            </table>
        </div>
      </div>
    </div>
  );
}

export default EmailCenter;
