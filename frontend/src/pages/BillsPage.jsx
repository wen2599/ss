import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { getEmails, deleteBill } from '../api.js'; // 导入 deleteBill
import { useAuth } from '../context/AuthContext.jsx';
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate();
    const { isAuthenticated } = useAuth();

    const handleBillClick = (id) => {
        navigate(`/bill/${id}`);
    };

    const handleDeleteBill = async (id, event) => {
        event.stopPropagation(); // 阻止事件冒泡到父级的 handleBillClick
        if (window.confirm('您确定要删除此账单吗？')) {
            try {
                const response = await deleteBill(id);
                if (response.status === 'success') {
                    setEmails(emails.filter(email => email.id !== id));
                    alert(response.message); // 显示成功消息
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
        if (!isAuthenticated) {
            navigate('/login');
            return;
        }

        const fetchEmails = async () => {
            try {
                setLoading(true);
                const response = await getEmails();
                if (response.status === 'success') {
                    setEmails(response.emails);
                } else {
                    setError(response.message || '无法获取账单列表');
                }
            } catch (err) {
                setError('获取账单失败，请稍后重试。');
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchEmails();
    }, [isAuthenticated, navigate]);

    if (loading) {
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
