import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getBills } from '../api'; // Re-using getBills to fetch a single bill by filtering
import './BillDetailsPage.css';

const BillDetailsPage = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [bill, setBill] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchBillDetails = async () => {
      try {
        setLoading(true);
        const data = await getBills(); // Fetches all bills for the user
        if (data.success && data.bills) {
          const foundBill = data.bills.find(b => b.id === parseInt(id));
          if (foundBill) {
            setBill(foundBill);
          } else {
            setError('未找到账单。');
          }
        } else {
          setError(data.error || '获取账单详情失败。');
        }
      } catch (err) {
        setError(err.message || '获取账单详情时发生错误。');
      } finally {
        setLoading(false);
      }
    };

    fetchBillDetails();
  }, [id]);

  if (loading) return <div>正在加载账单详情...</div>;
  if (error) return <div className="alert error">错误：{error}</div>;
  if (!bill) return <div className="alert error">未找到账单。</div>;

  return (
    <div className="bill-details-page">
      <h1>账单详情</h1>
      <div className="bill-info">
        <p><strong>主题：</strong> {bill.subject}</p>
        <p><strong>金额：</strong> {bill.amount ? `$${parseFloat(bill.amount).toFixed(2)}` : 'N/A'}</p>
        <p><strong>到期日期：</strong> {bill.due_date || 'N/A'}</p>
        <p><strong>状态：</strong> {bill.status}</p>
        <p><strong>接收时间：</strong> {new Date(bill.received_at).toLocaleString()}</p>
        {bill.is_lottery === 1 && (
          <p><strong>彩票号码：</strong> {bill.lottery_numbers || 'N/A'}</p>
        )}
        {/* Display raw email content (for debugging/advanced users) */}
        {/* <div className="raw-email-content">
          <h3>Raw Email Content:</h3>
          <pre>{bill.raw_email}</pre>
        </div> */}
      </div>
      <button onClick={() => navigate('/bills')} className="btn mt-3">返回账单列表</button>
    </div>
  );
};

export default BillDetailsPage;
