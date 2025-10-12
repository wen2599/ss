import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');

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

    const filteredEmails = emails.filter(email =>
        email.subject.toLowerCase().includes(searchTerm.toLowerCase()) ||
        email.from.toLowerCase().includes(searchTerm.toLowerCase())
    );

    if (loading) {
        return <div className="page-container loading">加载中...</div>;
    }

    if (error) {
        return <div className="page-container error-message">错误: {error}</div>;
    }

    return (
        <div className="page-container bills-container">
            <h1 className="page-title">账单中心</h1>
            <div className="toolbar">
                <input
                    type="text"
                    placeholder="搜索账单主题或发件人..."
                    className="search-input"
                    value={searchTerm}
                    onChange={e => setSearchTerm(e.target.value)}
                />
            </div>
            <div className="email-list">
                {filteredEmails.length > 0 ? filteredEmails.map(email => (
                    <Link to={`/bill/${email.id}`} key={email.id} className="email-item-link">
                        <div className="email-item card">
                            <div className="email-subject">{email.subject}</div>
                            <div className="email-from">发件人: {email.from}</div>
                            <div className="email-date">{new Date(email.created_at).toLocaleString()}</div>
                        </div>
                    </Link>
                )) : (
                    <p>没有找到与“{searchTerm}”相关的账单。</p>
                )}
            </div>
        </div>
    );
};

export default BillsPage;