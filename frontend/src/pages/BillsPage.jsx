import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { getBills } from '../api.js';
import './BillsPage.css';

const BillsPage = () => {
    const [bills, setBills] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate();

    const handleBillClick = (id) => {
        navigate(`/bill/${id}`);
    };

    useEffect(() => {
        const fetchBills = async () => {
            try {
                setLoading(true);
                const response = await getBills();
                if (response.status === 'success') {
                    setBills(response.bills);
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

        fetchBills();
    }, []);

    if (loading) {
        return <div className="loading">正在加载账单...</div>;
    }

    if (error) {
        return <div className="error-message">{error}</div>;
    }

    return (
        <div className="bills-page">
            <h1>我的电子账单</h1>
            {bills.length > 0 ? (
                <ul className="bill-list">
                    {bills.map((bill) => (
                        <li key={bill.id} className="bill-item" onClick={() => handleBillClick(bill.id)}>
                            <div className="bill-sender">{bill.sender}</div>
                            <div className="bill-subject">{bill.subject}</div>
                            <div className="bill-amount">{`${bill.amount} ${bill.currency}`}</div>
                            <div className="bill-date">{new Date(bill.bill_date).toLocaleDateString('zh-CN')}</div>
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
