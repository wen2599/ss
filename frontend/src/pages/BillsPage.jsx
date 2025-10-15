import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { getEmails } from '../api.js';
import { useAuth } from '../context/AuthContext.jsx'; // 导入 useAuth 钩子
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate();
    const { user, isAuthenticated } = useAuth(); // 使用 useAuth 获取用户和认证状态

    const handleBillClick = (id) => {
        navigate(`/bill/${id}`);
    };

    useEffect(() => {
        // 如果用户未认证，重定向到登录页面
        if (!isAuthenticated) {
            navigate('/login');
            return; // 阻止后续的API调用
        }

        const fetchEmails = async () => {
            try {
                setLoading(true);
                const response = await getEmails();
                if (response.status === 'success') { // 检查后端返回的 status 字段
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
    }, [isAuthenticated, navigate]); // 添加 isAuthenticated 和 navigate 到依赖数组

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
                        <li key={email.id} className="email-item" onClick={() => handleBillClick(email.id)}>
                            <div className="email-sender">{email.sender}</div>
                            <div className="email-subject">{email.subject}</div>
                            <div className="email-date">{new Date(email.created_at).toLocaleDateString('zh-CN')}</div>
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
