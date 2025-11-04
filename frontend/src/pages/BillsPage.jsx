import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { getEmails, deleteBill } from '../api.js';
import { useAuth } from '../context/AuthContext.jsx';
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true); // This state is for fetching emails
    const [error, setError] = useState(null);
    const navigate = useNavigate();
    // Get isAuthenticated and the new loading state from AuthContext
    const { isAuthenticated, loading: authLoading } = useAuth();

    const handleBillClick = (id) => {
        navigate(`/bill/${id}`);
    };

    const handleDeleteBill = async (id, event) => {
        event.stopPropagation();
        if (window.confirm('您确定要删除此账单吗？')) {
            try {
                const response = await deleteBill(id);
                if (response.status === 'success') {
                    setEmails(emails.filter(email => email.id !== id));
                    alert(response.message);
                } else {
                    setError(response.message || '删除账单失败');
                }
            } catch (err) {
                setError(err.message || '删除账单时发生错误。');
                console.error(err);
            }
        }
    };

    useEffect(() => {
        // Don't do anything until the auth check is complete
        if (authLoading) {
            return;
        }

        // If not authenticated after the check, redirect to login
        if (!isAuthenticated) {
            navigate('/login');
            return;
        }

        // Now that we're authenticated, fetch the emails
        const fetchEmails = async () => {
            try {
                setLoading(true); // Start loading emails
                const response = await getEmails();
                if (response.status === 'success') {
                    setEmails(response.emails);
                } else {
                    setError(response.message || '无法获取账单列表');
                }
            } catch (err) {
                // The error might be a 401 if the session expired between the auth check and this fetch
                if (err.message.includes('401')) {
                    setError('您的会话已过期，请重新登录。');
                    navigate('/login');
                } else {
                    setError('获取账单失败，请稍后重试。');
                }
                console.error(err);
            } finally {
                setLoading(false); // Finish loading emails
            }
        };

        fetchEmails();
        // This effect should run when the auth state is confirmed
    }, [isAuthenticated, authLoading, navigate]);

    // Show a loading message while either auth check or email fetch is in progress.
    if (authLoading || loading) {
        return <div className="loading">正在加载账单...</div>;
    }

    if (error) {
        return <div className="error-message">{error}</div>;
    }

    return (
        <div className="bills-page">
            <h1>我的电子账单</h1>
            {emails.length > 0 ? (
                <ul className="email-list">
                    {emails.map((email) => (
                        <li key={email.id} className="email-item">
                            <div className="email-content" onClick={() => handleBillClick(email.id)}>
                                <div className="email-sender">{email.sender}</div>
                                <div className="email-subject">{email.subject}</div>
                                <div className="email-date">{new Date(email.created_at).toLocaleDateString('zh-CN')}</div>
                            </div>
                            <button
                                className="delete-button"
                                onClick={(event) => handleDeleteBill(email.id, event)}
                            >
                                删除
                            </button>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>您当前没有任何账单。</p>
            )}
        </div>
    );
};

export default BillsPage;
