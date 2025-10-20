import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getBillDetails, processEmailWithAI, getEmailById } from '../api.js'; // Added getEmailById back for AI processing
import './BillDetailsPage.css';

const BillDetailsPage = () => {
    const [bill, setBill] = useState(null);
    const [emailContent, setEmailContent] = useState(null); // To store original email HTML
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(null);
    const { id } = useParams();

    const fetchBillAndEmailDetails = async () => {
        try {
            setLoading(true);
            setError(null);

            // Fetch bill details
            const billResponse = await getBillDetails(id);
            if (billResponse.status === 'success' && billResponse.data && billResponse.data.bill) {
                const billData = billResponse.data.bill;
                setBill(billData);

                // If bill has an associated email_id, fetch original email content
                if (billData.email_id) {
                    const emailResponse = await getEmailById(billData.email_id);
                    if (emailResponse.status === 'success' && emailResponse.data && emailResponse.data.email) {
                        setEmailContent(emailResponse.data.email.html_content);
                    } else {
                        console.warn("Could not fetch associated email content for bill ID:", billData.id);
                    }
                }

            } else {
                throw new Error(billResponse.message || '未找到该账单');
            }
        } catch (err) {
            console.error('获取账单详情失败:', err);
            setError(err.message || '获取账单详情失败，请稍后重试。');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchBillAndEmailDetails();
    }, [id]);

    const handleProcessAI = async () => {
        if (!bill || !bill.email_id) {
            setError('没有关联的电子邮件ID，无法进行AI处理。');
            return;
        }
        setProcessing(true);
        setError(null);
        try {
            const response = await processEmailWithAI(bill.email_id);
            if (response.status === 'success' && response.data) {
                // If AI processing updates bill details, you might want to refetch or update state
                // For now, let's assume it directly returns structured data or an update message
                alert(response.message || 'AI处理成功！');
                // Re-fetch bill details to get any updates from AI processing
                fetchBillAndEmailDetails(); 
            } else {
                throw new Error(response.message || 'AI处理失败');
            }
        } catch (err) {
            console.error('AI处理失败:', err);
            setError(err.message || 'AI处理失败，请稍后重试。');
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

    if (!bill) {
        return <div className="not-found">未找到该账单。</div>;
    }

    return (
        <div className="bill-details-container">
            <Link to="/bills" className="back-link">← 返回账单列表</Link>
            <div className="card">
                <h1 className="bill-name">{bill.bill_name || '未知账单'}</h1>
                <p className="bill-status">状态: {bill.status || 'N/A'}</p>

                {/* AI Process Button: Only show if there's an associated email and it hasn't been processed (or needs reprocessing) */}
                {bill.email_id && (
                    <button onClick={handleProcessAI} disabled={processing} className="ai-process-button">
                        {processing ? '正在处理...' : '重新AI处理账单相关邮件'}
                    </button>
                )}

                <div className="structured-data">
                    <h2>账单详情</h2>
                    <div className="data-grid">
                        <div className="data-item"><strong>金额:</strong> {bill.amount ? `¥${bill.amount}` : 'N/A'}</div>
                        <div className="data-item"><strong>截止日期:</strong> {bill.due_date || 'N/A'}</div>
                        <div className="data-item"><strong>关联邮件ID:</strong> {bill.email_id || '无'}</div>
                        <div className="data-item"><strong>创建时间:</strong> {new Date(bill.created_at).toLocaleString() || 'N/A'}</div>
                    </div>
                </div>

                {/* Display original email content if available */}
                {emailContent && (
                    <div className="original-email-section">
                        <h3>原始邮件内容</h3>
                        <div className="bill-body" dangerouslySetInnerHTML={{ __html: emailContent }} />
                    </div>
                )}
            </div>
        </div>
    );
};

export default BillDetailsPage;