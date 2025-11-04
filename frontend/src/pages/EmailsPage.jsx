import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext.jsx';
import './EmailsPage.css'; // Assuming you'll create a CSS file for this page

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

const EmailsPage = () => {
    const { user } = useAuth();
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchEmails = async () => {
            if (!user || !user.token) {
                setError('用户未认证。');
                setLoading(false);
                return;
            }
            try {
                setLoading(true);
                setError(null);
                const response = await axios.get(`${API_BASE_URL}/index.php?endpoint=get_emails`, {
                    headers: {
                        'Authorization': `Bearer ${user.token}`
                    },
                    withCredentials: true
                });
                
                if (response.data.status === 'success') {
                    setEmails(response.data.emails);
                } else {
                    setError(response.data.message || '获取邮件失败。');
                }
            } catch (err) {
                console.error('Failed to fetch emails:', err);
                setError(err.response?.data?.message || err.message || 'Failed to fetch emails.');
            } finally {
                setLoading(false);
            }
        };

        fetchEmails();
    }, [user]);

    if (loading) {
        return <div className="emails-page-container">加载中...</div>;
    }

    if (error) {
        return <div className="emails-page-container error">错误: {error}</div>;
    }

    return (
        <div className="emails-page-container">
            <h1>邮件原文</h1>
            {emails.length === 0 ? (
                <p>没有可用的邮件。</p>
            ) : (
                <div className="emails-list">
                    {emails.map((email) => (
                        <div key={email.id} className="email-card">
                            <h2>发件人: {email.sender}</h2>
                            <p><strong>主题:</strong> {email.subject}</p>
                            <p><strong>日期:</strong> {new Date(email.received_at).toLocaleString()}</p>
                            <div className="email-body-preview">
                                <h3>邮件正文:</h3>
                                <pre>{email.body_plain}</pre> {/* Display plain text body */}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default EmailsPage;