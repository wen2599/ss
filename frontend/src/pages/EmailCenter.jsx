import { useState, useEffect, useCallback } from 'react';
import EmailUpload from '../components/EmailUpload'; // 导入上传组件

// 样式保持不变
const tableStyles = { width: '100%', borderCollapse: 'collapse', marginTop: '1.5rem' };
const thStyles = { borderBottom: '2px solid var(--accent-color)', padding: '0.75rem', textAlign: 'left', color: 'var(--text-secondary)', textTransform: 'uppercase', fontSize: '0.8rem' };
const tdStyles = { borderBottom: '1px solid var(--card-bg)', padding: '0.75rem' };

function EmailCenter() {
  const [emails, setEmails] = useState([]);
  const [error, setError] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  // 使用 useCallback 包装 fetchEmails，以便在上传成功后可以安全地调用它
  const fetchEmails = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await fetch('/get_emails', { credentials: 'include' });
      if (!response.ok) throw new Error(`HTTP 错误！状态: ${response.status}`);
      
      const data = await response.json();
      if (data.error) throw new Error(data.error);
      
      setEmails(data.emails || []);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  }, []); // 空依赖数组，因为 fetchEmails 自身没有外部依赖

  // 初次加载时获取邮件
  useEffect(() => {
    fetchEmails();
  }, [fetchEmails]);

  // 当上传成功时，这个函数会被调用
  const handleUploadSuccess = () => {
    console.log('上传成功！正在刷新邮件列表...');
    fetchEmails();
  };

  return (
    <div className="lottery-card" style={{ maxWidth: '1200px' }}>
      {/* 将 EmailUpload 组件放置在此处 */}
      <EmailUpload onUploadSuccess={handleUploadSuccess} />
      
      <hr style={{ border: 'none', borderTop: '1px solid var(--card-bg)', margin: '2rem 0' }} />

      <h2>收件箱</h2>

      {isLoading && <p>正在更新邮件列表...</p>}
      {!isLoading && error && <p className="error">错误: {error}</p>}
      
      {!isLoading && !error && (
        <div style={{ overflowX: 'auto' }}>
          <table style={tableStyles}>
            <thead>
              <tr>
                <th style={thStyles}>日期</th>
                <th style={thStyles}>发件人</th>
                <th style={thStyles}>主题</th>
                <th style={thStyles}>账单金额</th>
              </tr>
            </thead>
            <tbody>
              {emails.length > 0 ? (
                emails.map((email, index) => (
                  <tr key={email.id || index}> {/* 使用 id 或 index 作为 key */}
                    <td style={tdStyles}>{new Date(email.date).toLocaleString()}</td>
                    <td style={tdStyles}>{email.from}</td>
                    <td style={tdStyles}>{email.subject}</td>
                    <td style={tdStyles}>{email.amount ? `$${email.amount}` : 'N/A'}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="4" style={{ ...tdStyles, textAlign: 'center', padding: '2rem' }}>
                    收件箱中没有找到账单邮件。您可以尝试上传 .eml 文件。
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export default EmailCenter;
