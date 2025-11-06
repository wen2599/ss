import { useState, useEffect } from 'react';
import api from '../services/api';

const DashboardPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchEmails = async () => {
        setLoading(true);
        try {
            const response = await api.get('/email.php?action=list');
            if (response.data.success) {
                setEmails(response.data.emails);
            } else {
                setError(response.data.message);
            }
        } catch (err) {
            setError('获取电子邮件失败。');
        }
        setLoading(false);
    };

    useEffect(() => {
        fetchEmails();
        const interval = setInterval(fetchEmails, 30000); // Refresh every 30 seconds
        return () => clearInterval(interval);
    }, []);

    const handleProcessEmail = async (emailId) => {
        try {
            const response = await api.get(`/email.php?action=process&id=${emailId}`);
            if (response.data.success) {
                // Refresh the list to show the updated status and result
                fetchEmails(); 
            } else {
                alert(`处理失败： ${response.data.message}`);
            }
        } catch (err) {
            alert('处理电子邮件时发生错误。');
        }
    };

    return (
        <div>
            <div className="dashboard-header">
                <h2>您提交的电子邮件</h2>
                <button onClick={fetchEmails} disabled={loading}>
                    {loading ? '刷新中...' : '刷新'}
                </button>
            </div>

            {error && <p className="error-message">{error}</p>}

            <div className="email-list">
                {emails.length === 0 && !loading && <p style={{textAlign: 'center', padding: '2rem'}}>未找到电子邮件。请发送电子邮件至您指定的地址以开始。</p>}
                
                {emails.map(email => (
                    <div key={email.id} className="email-item">
                        <div className="email-item-content">
                           <h3>收到： {new Date(email.received_at).toLocaleString()}</h3>
                           <p>状态： <span className={`status-badge status-${email.status.toLowerCase()}`}>{email.status}</span></p>
                           {email.status === 'pending' && (
                                <button onClick={() => handleProcessEmail(email.id)} style={{marginTop: '1rem'}}>
                                    立即处理
                                </button>
                           )}
                           {email.settlement_details && (
                                <div className="settlement-slip">
                                    <h4>结算单：</h4>
                                    <pre>{email.settlement_details}</pre>
                                </div>
                           )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default DashboardPage;
