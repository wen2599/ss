import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getEmailById, processEmailWithAI } from '../api.js';
import './BillDetailsPage.css';

const BillDetailsPage = () => {
    const [email, setEmail] = useState(null);
    const [structuredData, setStructuredData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(null);
    const { id } = useParams();

    const fetchEmailDetails = async () => {
        try {
            setLoading(true);
            const response = await getEmailById(id);
            if (response.status === 'success' && response.emails && response.emails.length > 0) {
                const emailData = response.emails[0];
                setEmail(emailData);
                // If the email is already processed, set the structured data
                if (emailData.is_processed) {
                    setStructuredData({
                        vendor_name: emailData.vendor_name,
                        bill_amount: emailData.bill_amount,
                        currency: emailData.currency,
                        due_date: emailData.due_date,
                        invoice_number: emailData.invoice_number,
                        category: emailData.category,
                    });
                }
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

    useEffect(() => {
        fetchEmailDetails();
    }, [id]);

    const handleProcessAI = async () => {
        setProcessing(true);
        setError(null);
        try {
            const response = await processEmailWithAI(id);
            if (response.status === 'success') {
                setStructuredData(response.data);
                // Also update the email state to mark as processed to hide the button
                setEmail(prev => ({ ...prev, is_processed: true }));
            } else {
                throw new Error(response.message || 'AI处理失败');
            }
        } catch (error) {
            console.error('AI处理失败:', error);
            setError('AI处理失败，请稍后重试。');
        } finally {
            setProcessing(false);
        }
    };

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

                {/* AI Process Button */}
                {!email.is_processed && !structuredData && (
                    <button onClick={handleProcessAI} disabled={processing} className="ai-process-button">
                        {processing ? '正在处理...' : '使用AI整理表单'}
                    </button>
                )}

                {/* Display Structured Data if available */}
                {structuredData ? (
                    <div className="structured-data">
                        <h2>账单详情</h2>
                        <div className="data-grid">
                            <div className="data-item"><strong>商家:</strong> {structuredData.vendor_name || 'N/A'}</div>
                            <div className="data-item"><strong>金额:</strong> {structuredData.bill_amount ? `${structuredData.bill_amount} ${structuredData.currency || ''}`.trim() : 'N/A'}</div>
                            <div className="data-item"><strong>截止日期:</strong> {structuredData.due_date || 'N/A'}</div>
                            <div className="data-item"><strong>发票号:</strong> {structuredData.invoice_number || 'N/A'}</div>
                            <div className="data-item"><strong>分类:</strong> {structuredData.category || 'N/A'}</div>
                        </div>
                    </div>
                ) : (
                    <div className="bill-body" dangerouslySetInnerHTML={{ __html: email.html_content }} />
                )}
            </div>
        </div>
    );
};

export default BillDetailsPage;