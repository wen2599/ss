import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import './BillDetailsPage.css';

const BillDetailsPage = () => {
    const [email, setEmail] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState('details'); // Default to 'details'
    const { id } = useParams();

    useEffect(() => {
        setLoading(true);
        fetch(`/api/get_emails?id=${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应错误');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.emails.length > 0) {
                    setEmail(data.emails[0]);
                } else {
                    throw new Error(data.message || '未找到该邮件');
                }
                setLoading(false);
            })
            .catch(error => {
                console.error('获取邮件详情时出错:', error);
                setError(error.message);
                setLoading(false);
            });
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

    const ParsedDetails = () => (
        <div className="parsed-data">
            {email.betting_slips && email.betting_slips.length > 0 ? (
                <div className="betting-slips-container">
                    <h3>投注详情</h3>
                    {email.betting_slips.map((slip, index) => (
                        <div key={index} className="betting-slip-card">
                            <p><strong>彩票类型:</strong> {slip.lottery_type}</p>
                            <p><strong>期号:</strong> {slip.issue_number}</p>
                            <p><strong>投注号码:</strong> {slip.betting_numbers}</p>
                            <p><strong>投注金额:</strong> {slip.betting_amount}</p>
                            <p><strong>投注时间:</strong> {new Date(slip.created_at).toLocaleString()}</p>
                        </div>
                    ))}
                </div>
            ) : (
                <p>此邮件中未找到可解析的投注详情。</p>
            )}
        </div>
    );

    const RawHtml = () => (
        <div className="raw-html-content" dangerouslySetInnerHTML={{ __html: email.html_content }} />
    );


    return (
        <div className="page-container bill-details-container">
            <div className="card">
                <h1 className="bill-subject">{email.subject}</h1>
                <div className="bill-meta">
                    <span><b>发件人:</b> {email.from}</span>
                    <span><b>收件人:</b> {email.to}</span>
                    <span><b>日期:</b> {new Date(email.created_at).toLocaleString()}</span>
                </div>
                <hr className="divider" />

                <div className="tabs">
                    <button
                        className={`tab ${activeTab === 'details' ? 'active' : ''}`}
                        onClick={() => setActiveTab('details')}
                    >
                        账单详情
                    </button>
                    <button
                        className={`tab ${activeTab === 'raw' ? 'active' : ''}`}
                        onClick={() => setActiveTab('raw')}
                    >
                        邮件原文
                    </button>
                </div>

                <div className="tab-content">
                    {activeTab === 'details' ? <ParsedDetails /> : <RawHtml />}
                </div>
            </div>
        </div>
    );
};

export default BillDetailsPage;