import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { getBills, deleteBill } from '../api.js'; // Updated to use getBills
import { useAuth } from '../context/AuthContext.jsx';
import './BillsPage.css';

const BillsPage = () => {
    const [bills, setBills] = useState([]); // Renamed from emails to bills
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate();
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
                    setBills(bills.filter(bill => bill.id !== id));
                    alert(response.data || '删除成功');
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
        if (authLoading) {
            return;
        }

        if (!isAuthenticated) {
            navigate('/login');
            return;
        }

        const fetchBills = async () => {
            try {
                setLoading(true);
                const response = await getBills(); // Use getBills instead of getEmails
                if (response.status === 'success' && response.data && response.data.bills) {
                    setBills(response.data.bills); // Set bills from response.data.bills
                } else {
                    setError(response.message || '无法获取账单列表');
                }
            } catch (err) {
                if (err.message.includes('401')) {
                    setError('您的会话已过期，请重新登录。');
                    navigate('/login');
                } else {
                    setError('获取账单失败，请稍后重试。');
                }
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchBills();
    }, [isAuthenticated, authLoading, navigate]);

    if (authLoading || loading) {
        return <div className="loading">正在加载账单...</div>;
    }

    if (error) {
        return <div className="error-message">{error}</div>;
    }

    return (
        <div className="bills-page">
            <h1>我的电子账单</h1>
            {bills.length > 0 ? (
                <ul className="bill-list"> {/* Updated class name */}
                    {bills.map((bill) => (
                        <li key={bill.id} className="bill-item"> {/* Updated class name */}
                            <div className="bill-content" onClick={() => handleBillClick(bill.id)}>
                                <div className="bill-name">{bill.bill_name || 'N/A'}</div>
                                <div className="bill-amount">¥{bill.amount || '0.00'}</div>
                                <div className="bill-due-date">到期日: {new Date(bill.due_date).toLocaleDateString('zh-CN')}</div>
                            </div>
                            <button
                                className="delete-button"
                                onClick={(event) => handleDeleteBill(bill.id, event)}
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
