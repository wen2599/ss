import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

// Component to render the structured settlement details
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
    <div className="details-container" style={{ padding: '10px' }}>
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

// New component for the two-panel view
function BillDetailsViewer({ bill, onPrev, onNext, isPrevDisabled, isNextDisabled }) {
  return (
    <div className="bill-details-viewer">
      <div className="navigation-buttons">
        <button onClick={onPrev} disabled={isPrevDisabled}>&larr; 上一条</button>
        <button onClick={onNext} disabled={isNextDisabled}>下一条 &rarr;</button>
      </div>
      <div className="panels-container">
        <div className="panel">
          <h3>邮件原文</h3>
          <pre className="raw-content-panel">{bill.raw_content}</pre>
        </div>
        <div className="panel">
          <h3>结算内容</h3>
          <SettlementDetails details={bill.settlement_details} />
        </div>
      </div>
    </div>
  );
}


function BillsPage() {
  const [bills, setBills] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedBillIndex, setSelectedBillIndex] = useState(null);
  const { token } = useAuth();

  useEffect(() => {
    const fetchBills = async () => {
      setIsLoading(true);
      setError('');
      try {
        const response = await fetch('/get_bills', {
          method: 'GET',
          headers: { 'Content-Type': 'application/json' }
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
  }, [token]);

  const handleSelectBill = (index) => {
    setSelectedBillIndex(index);
  };

  const handlePrevBill = () => {
    if (selectedBillIndex !== null && selectedBillIndex > 0) {
      setSelectedBillIndex(selectedBillIndex - 1);
    }
  };

  const handleNextBill = () => {
    if (selectedBillIndex !== null && selectedBillIndex < bills.length - 1) {
      setSelectedBillIndex(selectedBillIndex + 1);
    }
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

  const selectedBill = selectedBillIndex !== null ? bills[selectedBillIndex] : null;

  return (
    <div className="bills-container">
      <Link to="/" className="back-link">&larr; 返回主页</Link>
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
            {bills.map((bill, index) => (
              <tr
                key={bill.id}
                onClick={() => handleSelectBill(index)}
                className={selectedBillIndex === index ? 'selected-row' : ''}
              >
                <td>{bill.id}</td>
                <td>{new Date(bill.created_at).toLocaleString()}</td>
                <td>{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
                <td>{renderStatus(bill.status)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {selectedBill && (
        <BillDetailsViewer
          bill={selectedBill}
          onPrev={handlePrevBill}
          onNext={handleNextBill}
          isPrevDisabled={selectedBillIndex === 0}
          isNextDisabled={selectedBillIndex === bills.length - 1}
        />
      )}
    </div>
  );
}

export default BillsPage;
