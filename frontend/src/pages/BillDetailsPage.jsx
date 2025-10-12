import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
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
                    throw new Error(response.message || '未找到该邮件');
                }
            } catch (error) {
                console.error('获取邮件详情时出错:', error);
                setError(error.message);
            } finally {
                setLoading(false);
            }
        };

        fetchEmail();
    }, [id]);

    if (loading) {
        return <div className="page-container loading">加载中...</div>;
    }

    if (error) {
        return <div className="page-container error-message">错误: {error}</div>;
    }

    if (!email) {
        return <div className="page-container not-found">未找到该邮件。</div>;
    }

    return (
        <div className="page-container bill-details-container">
            <div className="card">
                <h1 className="bill-subject">{email.subject}</h1>
                <div className="bill-meta">
                    <span><b>发件人:</b> {email.sender}</span>
                    <span><b>收件人:</b> {email.recipient}</span>
                    <span><b>日期:</b> {new Date(email.created_at).toLocaleString()}</span>
                </div>
                <hr className="divider" />
                <div className="bill-body" dangerouslySetInnerHTML={{ __html: email.html_content }} />
            </div>
        </div>
    );
};

export default BillDetailsPage;