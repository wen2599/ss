import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

// New component to render the settlement details
function SettlementDetails({ details }) {
  if (!details) {
    return <div className="details-container">没有详细信息。</div>;
  }

  let parsedDetails;
  try {
    parsedDetails = JSON.parse(details);
  } catch (e) {
    return <div className="details-container">无法解析详细信息。</div>;
  }

  const { zodiac_bets, number_bets, summary } = parsedDetails;

  return (
    <div className="details-container" style={{ padding: '10px', backgroundColor: '#f9f9f9' }}>
      <h4>结算单详情</h4>
      {zodiac_bets && zodiac_bets.length > 0 && (
        <div className="details-section">
          <strong>生肖投注:</strong>
          <ul>
            {zodiac_bets.map((bet, index) => (
              <li key={index}>
                `{bet.zodiac}`: {bet.numbers.join(', ')} (<strong>{bet.cost}元</strong>)
              </li>
            ))}
          </ul>
        </div>
      )}
      {number_bets && number_bets.numbers && number_bets.numbers.length > 0 && (
        <div className="details-section">
          <strong>单独号码投注:</strong>
          <p>{number_bets.numbers.join(', ')} (<strong>{number_bets.cost}元</strong>)</p>
        </div>
      )}
      {summary && (
        <div className="details-summary">
          <strong>总结:</strong>
          <p>总计: <strong>{summary.total_unique_numbers}</strong> 个号码</p>
          <p>总金额: <strong>{summary.total_cost}</strong> 元</p>
        </div>
      )}
    </div>
  );
}


function BillsPage() {
  const [bills, setBills] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedBillId, setSelectedBillId] = useState(null);
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

  const toggleDetails = (billId) => {
    setSelectedBillId(selectedBillId === billId ? null : billId);
  };

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
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            {bills.map((bill) => (
              <React.Fragment key={bill.id}>
                <tr>
                  <td>{bill.id}</td>
                  <td>{new Date(bill.created_at).toLocaleString()}</td>
                  <td>{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
                  <td>{renderStatus(bill.status)}</td>
                  <td>
                    <button onClick={() => toggleDetails(bill.id)} disabled={!bill.settlement_details}>
                      {selectedBillId === bill.id ? '收起' : '详情'}
                    </button>
                  </td>
                </tr>
                {selectedBillId === bill.id && (
                  <tr>
                    <td colSpan="5">
                      <SettlementDetails details={bill.settlement_details} />
                    </td>
                  </tr>
                )}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

export default BillsPage;
