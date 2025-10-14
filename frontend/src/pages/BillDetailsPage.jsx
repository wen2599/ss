import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getBillById } from '../api.js';
import './BillDetailsPage.css';

const BillDetailsPage = () => {
    const [bill, setBill] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { id } = useParams();

    useEffect(() => {
        const fetchBill = async () => {
            try {
                setLoading(true);
                const response = await getBillById(id);
                if (response.status === 'success' && response.bills.length > 0) {
                    setBill(response.bills[0]);
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

        fetchBill();
    }, [id]);

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
                <h1 className="bill-subject">{bill.subject}</h1>
                <div className="bill-meta">
                    <span><b>发件人:</b> {bill.sender}</span>
                    <span><b>账单日期:</b> {new Date(bill.bill_date).toLocaleDateString('zh-CN')}</span>
                    <span><b>金额:</b> {`${bill.amount} ${bill.currency}`}</span>
                </div>
                {bill.pdf_url && (
                    <div className="bill-actions">
                        <a href={bill.pdf_url} target="_blank" rel="noopener noreferrer" className="pdf-link">
                            查看 PDF 账单
                        </a>
                    </div>
                )}
                <div className="bill-body" dangerouslySetInnerHTML={{ __html: bill.html_content }} />
            </div>
        </div>
    );
};

export default BillDetailsPage;