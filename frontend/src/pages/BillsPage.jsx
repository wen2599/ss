import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

function SettlementDetails({ details, rawContent }) {
  if (!details) return <div className="details-container">没有详细信息。</div>;

  let parsedDetails;
  try {
    parsedDetails = typeof details === 'string' ? JSON.parse(details) : details;
  } catch (e) {
    return <div className="details-container">无法解析详细信息。</div>;
  }

  const { zodiac_bets, number_bets, summary } = parsedDetails;

  const settlementText = `
生肖投注:
${(zodiac_bets || []).map(bet => `  - ${bet.zodiac}: ${bet.numbers.join(', ')} (${bet.cost}元)`).join('\n')}

单独号码投注:
  - 号码: ${(number_bets?.numbers || []).join(', ')}
  - 金额: ${number_bets?.cost || 0}元

总结:
  - 总计: ${summary?.total_numbers_count ?? summary?.total_unique_numbers || 0} 个号码
  - 总金额: ${summary?.total_cost || 0} 元
  `;

  return (
    <div className="panels-container">
      <div className="panel">
        <h3>邮件原文</h3>
        <textarea readOnly value={rawContent}></textarea>
      </div>
      <div className="panel">
        <h3>结算内容</h3>
        <textarea readOnly value={settlementText.trim()}></textarea>
      </div>
    </div>
  );
}

function MultiSettlementDetails({ details, billId }) {
  const [markedIndexes, setMarkedIndexes] = useState(() => {
    const key = `bill_${billId}_marked`;
    const saved = localStorage.getItem(key);
    return saved ? JSON.parse(saved) : [];
  });

  let settlements;
  try {
    settlements = typeof details === 'string' ? JSON.parse(details) : details;
  } catch {
    return <div>无法解析结算详情。</div>;
  }

  const [currentIdx, setCurrentIdx] = useState(0);

  const handleMark = (index) => {
    const newMarked = [...new Set([...markedIndexes, index])];
    setMarkedIndexes(newMarked);
    localStorage.setItem(`bill_${billId}_marked`, JSON.stringify(newMarked));
  };

  const handleUnmark = (index) => {
    const newMarked = markedIndexes.filter(i => i !== index);
    setMarkedIndexes(newMarked);
    localStorage.setItem(`bill_${billId}_marked`, JSON.stringify(newMarked));
  };

  if (!Array.isArray(settlements) || settlements.length === 0) {
    return <div>没有详细信息。</div>;
  }

  const current = settlements[currentIdx];
  const isMarked = markedIndexes.includes(current.index);

  const { zodiac_bets, number_bets, summary } = current.result || {};
  const settlementText = `
生肖投注:
${(zodiac_bets || []).map(bet => `  - ${bet.zodiac}: ${bet.numbers.join(', ')} (${bet.cost}元)`).join('\n')}

单独号码投注:
  - 号码: ${(number_bets?.numbers || []).join(', ')}
  - 金额: ${number_bets?.cost || 0}元

总结:
  - 总计: ${summary?.total_numbers_count ?? summary?.total_unique_numbers || 0} 个号码
  - 总金额: ${summary?.total_cost || 0} 元
  `;

  const validSettlements = settlements.filter(s => !markedIndexes.includes(s.index));
  const totalNumbers = validSettlements.reduce((sum, s) => sum + (s.result?.summary?.total_numbers_count ?? s.result?.summary?.total_unique_numbers || 0), 0);
  const totalCost = validSettlements.reduce((sum, s) => sum + (s.result?.summary?.total_cost || 0), 0);

  return (
    <div>
      <div className="multi-details-nav">
        <button onClick={() => setCurrentIdx(idx => Math.max(idx - 1, 0))} disabled={currentIdx === 0}>上一条</button>
        <span>第 {currentIdx + 1} / {settlements.length} 条下注单</span>
        <button onClick={() => setCurrentIdx(idx => Math.min(idx + 1, settlements.length - 1))} disabled={currentIdx === settlements.length - 1}>下一条</button>
        <div style={{ marginTop: '10px' }}>
          {isMarked ? (
            <button onClick={() => handleUnmark(current.index)} style={{ color: 'orange', fontWeight: 'bold' }}>取消标记错误</button>
          ) : (
            <button onClick={() => handleMark(current.index)} style={{ color: 'red' }}>标记为错误</button>
          )}
          {isMarked && <span style={{ color: 'red', fontWeight: 'bold', marginLeft: '10px' }}>此条下注单已标记为错误</span>}
        </div>
      </div>
      <div className="panels-container">
        <div className="panel">
          <h3>下注内容</h3>
          <textarea readOnly value={current.raw}></textarea>
        </div>
        <div className="panel">
          <h3>结算结果</h3>
          <textarea readOnly value={settlementText.trim()}></textarea>
        </div>
      </div>
      <div className="multi-details-summary">
        <strong>未标记下注单统计：</strong>
        <p>总号码数：<strong>{totalNumbers}</strong> 个</p>
        <p>总金额：<strong>{totalCost}</strong> 元</p>
      </div>
    </div>
  );
}

function BillDetailsViewer({ bill, onPrev, onNext, isPrevDisabled, isNextDisabled }) {
  let isMulti = false;
  try {
    const parsed = JSON.parse(bill.settlement_details);
    isMulti = Array.isArray(parsed);
  } catch {}

  const viewerContent = isMulti
    ? <MultiSettlementDetails details={bill.settlement_details} billId={bill.id} />
    : <SettlementDetails details={bill.settlement_details} rawContent={bill.raw_content} />;

  return (
    <div className="bill-details-viewer">
      <div className="navigation-buttons">
        <button onClick={onPrev} disabled={isPrevDisabled}>&larr; 上一条</button>
        <button onClick={onNext} disabled={isNextDisabled}>下一条 &rarr;</button>
      </div>
      {viewerContent}
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
