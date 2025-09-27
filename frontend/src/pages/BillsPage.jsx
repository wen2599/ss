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
  // 判断是否是单条（老数据）
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
            <p>总计: <strong>{summary.total_unique_numbers}</strong> 个号码</p>
            <p>总金额: <strong>{summary.total_cost}</strong> 元</p>
          </div>
        )}
      </div>
    );
  }
  // 多条结算由 MultiSettlementDetails 渲染
  return null;
}

// 多条下注单窗口（全面移除标记功能，统计全部下注单）
function MultiSettlementDetails({ details, billId }) {
  let settlements;
  try {
    settlements = typeof details === 'string' ? JSON.parse(details) : details;
  } catch {
    return <div>无法解析结算详情。</div>;
  }

  const [currentIdx, setCurrentIdx] = useState(0);

  if (!Array.isArray(settlements) || settlements.length === 0) {
    return <div>没有详细信息。</div>;
  }

  const current = settlements[currentIdx];

  // 统计全部下注单
  const totalNumbers = settlements.reduce((sum, s) => sum + (s.result?.summary?.total_unique_numbers || 0), 0);
  const totalCost = settlements.reduce((sum, s) => sum + (s.result?.summary?.total_cost || 0), 0);

  return (
    <div className="multi-details-container">
      <div className="multi-details-nav">
        <button onClick={() => setCurrentIdx(idx => Math.max(idx - 1, 0))} disabled={currentIdx === 0}>上一条</button>
        <span>第 {currentIdx + 1} / {settlements.length} 条下注单</span>
        <button onClick={() => setCurrentIdx(idx => Math.min(idx + 1, settlements.length - 1))} disabled={currentIdx === settlements.length - 1}>下一条</button>
      </div>
      <div className="single-bet-section" style={{ margin: '10px 0', padding: '8px', border: '1px solid #eee', borderRadius: '8px' }}>
        <div>
          <strong>下注内容：</strong>
          <pre>{current.raw}</pre>
        </div>
        <div>
          <strong>结算结果：</strong>
          <SettlementDetails details={current.result} />
        </div>
      </div>
      <div className="multi-details-summary" style={{ marginTop: '16px', paddingTop: '8px', borderTop: '1px solid #ccc' }}>
        <strong>全部下注单统计：</strong>
        <p>总号码数：<strong>{totalNumbers}</strong> 个</p>
        <p>总金额：<strong>{totalCost}</strong> 元</p>
      </div>
    </div>
  );
}

function BillDetailsViewer({ bill, onPrev, onNext, isPrevDisabled, isNextDisabled }) {
  // 判断是否多条结算
  let isMulti = false;
  try {
    const parsed = JSON.parse(bill.settlement_details);
    isMulti = Array.isArray(parsed);
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
