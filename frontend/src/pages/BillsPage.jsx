import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

// 单条结算详情显示（兼容旧账单）
function SettlementDetails({ details }) {
  if (!details) return <div className="details-container">没有详细信息。</div>;

  let parsedDetails;
  try {
    parsedDetails = typeof details === 'string' ? JSON.parse(details) : details;
  } catch (e) {
    return <div className="details-container">无法解析详细信息。</div>;
  }
  // 新结构或老结构
  if (parsedDetails.zodiac_bets || parsedDetails.number_bets) {
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
            <p>号码总数: <strong>{summary.number_count ?? summary.total_unique_numbers}</strong> 个</p>
            <p>总金额: <strong>{summary.total_cost}</strong> 元</p>
          </div>
        )}
      </div>
    );
  }
  // 多条结算由 MultiSettlementDetails 渲染
  return null;
}

// 多条下注单窗口，每条下注单独展示结算，末尾展示全部总计
function MultiSettlementDetails({ details, billId }) {
  let settlementObj;
  try {
    settlementObj = typeof details === 'string' ? JSON.parse(details) : details;
  } catch {
    return <div>无法解析结算详情。</div>;
  }

  // 新结构：{ slips: [...], summary: {...} }
  const slips = Array.isArray(settlementObj) ? settlementObj : settlementObj?.slips;
  const summary = settlementObj?.summary;

  if (!slips || slips.length === 0) {
    return <div>没有详细信息。</div>;
  }

  return (
    <div className="multi-details-container">
      {slips.map((slip, idx) => (
        <div key={idx} className="single-bet-section" style={{ margin: '16px 0', padding: '8px', border: '1px solid #eee', borderRadius: '8px' }}>
          <div>
            <strong>时间点：</strong> {slip.time ?? `第${slip.index}段`}
          </div>
          <div>
            <strong>下注内容：</strong>
            <pre>{slip.raw}</pre>
          </div>
          <div>
            <strong>该条结算结果：</strong>
            <SettlementDetails details={slip.result} />
          </div>
        </div>
      ))}
      {summary && (
        <div className="multi-details-summary" style={{ marginTop: '24px', paddingTop: '12px', borderTop: '2px solid #ccc' }}>
          <strong>全部下注单总计：</strong>
          <p>所有号码总数：<strong>{summary.total_number_count}</strong> 个</p>
          <p>总金额：<strong>{summary.total_cost}</strong> 元</p>
        </div>
      )}
    </div>
  );
}

function BillDetailsViewer({ bill, onPrev, onNext, isPrevDisabled, isNextDisabled }) {
  // 判断是否多条结算（新结构：有slips数组）
  let isMulti = false;
  try {
    const parsed = JSON.parse(bill.settlement_details);
    isMulti = Array.isArray(parsed?.slips) || Array.isArray(parsed);
  } catch {}
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
          {isMulti
            ? <MultiSettlementDetails details={bill.settlement_details} billId={bill.id} />
            : <SettlementDetails details={bill.settlement_details} />}
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
  const { user, isAuthenticated } = useAuth();

  // fetch请求必须带credentials: 'include'
  const fetchBills = async () => {
    setIsLoading(true);
    setError('');
    try {
      const response = await fetch('/get_bills', {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include'
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

  useEffect(() => {
    if (isAuthenticated) {
      fetchBills();
    } else {
      setBills([]);
    }
  }, [isAuthenticated, user]);

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

  const handleDeleteBill = async (billId) => {
    if (!window.confirm(`您确定要删除账单 #${billId} 吗？此操作无法撤销。`)) {
      return;
    }
    try {
      const response = await fetch('/delete_bill', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bill_id: billId }),
        credentials: 'include'
      });
      const data = await response.json();
      if (data.success) {
        setBills(prevBills => prevBills.filter(bill => bill.id !== billId));
        if (selectedBillIndex !== null && bills[selectedBillIndex]?.id === billId) {
          setSelectedBillIndex(null);
        }
      } else {
        alert(`删除失败: ${data.error}`);
      }
    } catch (err) {
      alert('删除时发生错误。');
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
                <td>
                  <button onClick={(e) => { e.stopPropagation(); handleDeleteBill(bill.id); }} className="delete-button">
                    删除
                  </button>
                </td>
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
