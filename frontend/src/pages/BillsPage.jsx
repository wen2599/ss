import React, { useEffect, useState } from 'react';
import { getBills, deleteBill } from '../api';
import { Link } from 'react-router-dom';
import './BillsPage.css';

const BillsPage = () => {
  const [bills, setBills] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState(null);

  const fetchBills = async () => {
    try {
      setLoading(true);
      const data = await getBills();
      if (data.success) {
        setBills(data.bills);
      } else {
        setError(data.error || '获取账单失败。');
      }
    } catch (err) {
      setError(err.message || '获取账单时发生错误。');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBills();
  }, []);

  const handleDelete = async (billId) => {
    if (window.confirm('您确定要删除此账单吗？')) {
      try {
        const data = await deleteBill(billId);
        if (data.message) {
          setMessage(data.message);
          fetchBills(); // Refresh the list after deletion
        }
      } catch (err) {
        setError(err.message || '删除账单失败。');
      }
    }
  };

  if (loading) return <div>正在加载账单...</div>;
  if (error) return <div className="alert error">错误：{error}</div>;

  return (
    <div className="bills-page">
      <h1>我的账单</h1>
      {message && <div className="alert success">{message}</div>}
      {bills.length === 0 ? (
        <p>未找到任何账单。请开始将您的账单转发到您注册的邮箱！</p>
      ) : (
        <table className="bills-table">
          <thead>
            <tr>
              <th>主题</th>
              <th>金额</th>
              <th>到期日期</th>
              <th>状态</th>
              <th>接收时间</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            {bills.map((bill) => (
              <tr key={bill.id}>
                <td><Link to={`/bills/${bill.id}`}>{bill.subject}</Link></td>
                <td>{bill.amount ? `$${parseFloat(bill.amount).toFixed(2)}` : 'N/A'}</td>
                <td>{bill.due_date || 'N/A'}</td>
                <td>{bill.status}</td>
                <td>{new Date(bill.received_at).toLocaleDateString()}</td>
                <td className="actions">
                  <button onClick={() => handleDelete(bill.id)} className="btn btn-danger">删除</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default BillsPage;
