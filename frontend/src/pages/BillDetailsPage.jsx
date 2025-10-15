import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getEmailById } from '../api.js';
import './BillDetailsPage.css';

const BillDetailsPage = () => {
    const [email, setEmail] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { id } = useParams();

    useEffect(() => {
        const fetchEmail = async () => {
            try {
                setLoading(true);
                const response = await getEmailById(id);
                if (response.success && response.emails.length > 0) {
                    setEmail(response.emails[0]);
                } else {
                    throw new Error(response.message || '未找到该账单');
                }
            } catch (error) {
                console.error('获取账单详情失败:', error);
                setError('获取账单详情失败，请稍后重试。');
            } finally {
                setLoading(false);
            }
        };

        fetchEmail();
    }, [id]);

    if (loading) {
        return <div className="loading">正在加载...</div>;
    }

    if (error) {
        return <div className="error-message">{error}</div>;
    }

    if (!email) {
        return <div className="not-found">未找到该账单。</div>;
    }

    return (
        <div className="bill-details-container">
            <Link to="/bills" className="back-link">← 返回账单列表</Link>
            <div className="card">
                <h1 className="bill-subject">{email.subject}</h1>
                <div className="bill-meta">
                    <span><b>发件人:</b> {email.sender}</span>
                    <span><b>收件人:</b> {email.recipient}</span>
                    <span><b>日期:</b> {new Date(email.created_at).toLocaleString('zh-CN')}</span>
                </div>
                <div className="bill-body" dangerouslySetInnerHTML={{ __html: email.html_content }} />
            </div>
        </div>
    );
};

export default BillDetailsPage;