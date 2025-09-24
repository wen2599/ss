import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

function BillsPage() {
  const [bills, setBills] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const { token } = useAuth(); // Assuming token might be needed for auth header in the future

  useEffect(() => {
    const fetchBills = async () => {
      setIsLoading(true);
      setError('');
      try {
        const response = await fetch('/get_bills', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            // If you use token-based auth, you would add:
            // 'Authorization': `Bearer ${token}`
          }
        });

        const data = await response.json();

        if (data.success) {
          setBills(data.bills);
        } else {
          setError(data.error || 'Failed to fetch bills.');
        }
      } catch (err) {
        setError('An error occurred while fetching bills. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchBills();
  }, [token]); // Re-fetch if token changes, e.g., after login

  const renderStatus = (status) => {
    switch (status) {
      case 'processed':
        return <span className="status-processed">已处理</span>;
      case 'unrecognized':
        return <span className="status-unrecognized">无法识别</span>;
      default:
        return <span className="status-default">{status}</span>;
    }
  };

  if (isLoading) {
    return <div>正在加载您的账单...</div>;
  }

  if (error) {
    return <div className="error">{error}</div>;
  }

  return (
    <div className="bills-container">
      <h2>我的账单</h2>
      {bills.length === 0 ? (
        <p>您还没有任何账单记录。</p>
      ) : (
        <table className="bills-table">
          <thead>
            <tr>
              <th>账单ID</th>
              <th>创建时间</th>
              <th>总金额</th>
              <th>状态</th>
            </tr>
          </thead>
          <tbody>
            {bills.map((bill) => (
              <tr key={bill.id}>
                <td>{bill.id}</td>
                <td>{new Date(bill.created_at).toLocaleString()}</td>
                <td>{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
                <td>{renderStatus(bill.status)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

export default BillsPage;
