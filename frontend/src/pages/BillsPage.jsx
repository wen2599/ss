import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { getEmails } from '../api.js';
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate();

    const handleBillClick = (id) => {
        navigate(`/bill/${id}`);
    };

    useEffect(() => {
        const fetchEmails = async () => {
            try {
                setLoading(true);
                const response = await getEmails();
                if (response.success) {
                    setEmails(response.emails);
                } else {
                    setError(response.message || '无法获取邮件。');
                }
            } catch (err) {
                setError('无法获取邮件。');
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchEmails();
    }, []);

    if (loading) {
        return <div className="loading">正在加载邮件...</div>;
    }

    if (error) {
        return <div className="error-message">{error}</div>;
    }

    return (
        <div className="bills-page">
            <h1>您的电子账单</h1>
            {emails.length > 0 ? (
                <ul className="email-list">
                    {emails.map((email) => (
                        <li key={email.id} className="email-item" onClick={() => handleBillClick(email.id)}>
                            <div className="email-sender">{email.sender}</div>
                            <div className="email-subject">{email.subject}</div>
                            <div className="email-date">{new Date(email.received_at).toLocaleDateString()}</div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>您还没有任何账单邮件。</p>
            )}
        </div>
    );
};

export default BillsPage;
