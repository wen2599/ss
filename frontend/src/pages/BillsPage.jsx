import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom'; // Import useNavigate
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate(); // Initialize navigate

    useEffect(() => {
        fetch('/api/get_emails')
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应错误');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    setEmails(data.emails);
                } else {
                    throw new Error(data.message || '获取邮件失败');
                }
                setLoading(false);
            })
            .catch(error => {
                console.error('获取邮件列表时出错:', error);
                setError(error.message);
                setLoading(false);
            });
    }, []);

    const handleBillClick = (id) => {
        navigate(`/bill/${id}`); // Use navigate for programmatic navigation
    };

    if (loading) {
        return <div className="page-container loading">加载中...</div>;
    }

    if (error) {
        return <div className="page-container error-message">错误: {error}</div>;
    }

    return (
        <div className="page-container bills-container">
            <h1 className="page-title">账单中心</h1>
            <div className="email-list">
                {emails.length > 0 ? emails.map(email => (
                    // Use a div with an onClick handler instead of a Link
                    <div key={email.id} className="email-item card" onClick={() => handleBillClick(email.id)}>
                        <div className="email-subject">{email.subject}</div>
                        <div className="email-from">发件人: {email.from}</div>
                        <div className="email-date">{new Date(email.created_at).toLocaleString()}</div>
                    </div>
                )) : <p>沒有找到賬單郵件。</p>}
            </div>
        </div>
    );
};

export default BillsPage;