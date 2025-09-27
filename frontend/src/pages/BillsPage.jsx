import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

// 单条结算详情（表格美化）
function SettlementDetails({ details }) {
  if (!details) return <div className="details-container">没有详细信息。</div>;
  let parsedDetails;
  try {
    parsedDetails = typeof details === 'string' ? JSON.parse(details) : details;
  } catch (e) {
    return <div className="details-container">无法解析详细信息。</div>;
  }
  if (parsedDetails.zodiac_bets || parsedDetails.number_bets) {
    const { zodiac_bets, number_bets, summary } = parsedDetails;
    return (
      <div className="details-container" style={{ padding: '10px', overflowX: 'auto' }}>
        <table className="settlement-table">
          <thead>
            <tr>
              <th>类型</th>
              <th>内容</th>
              <th>金额</th>
            </tr>
          </thead>
          <tbody>
            {zodiac_bets && zodiac_bets.length > 0 && (
              zodiac_bets.map((bet, idx) => (
                <tr key={`zodiac-${idx}`}>
                  <td className="type-zodiac">生肖投注<br/><span className="zodiac-tag">{bet.zodiac}</span></td>
                  <td>{bet.numbers.join(', ')}</td>
                  <td className="amount">{bet.cost} 元</td>
                </tr>
              ))
            )}
            {number_bets && number_bets.numbers && number_bets.numbers.length > 0 && (
              <tr>
                <td className="type-number">单独号码投注</td>
                <td>{number_bets.numbers.join(', ')}</td>
                <td className="amount">{number_bets.cost} 元</td>
              </tr>
            )}
          </tbody>
          {summary && (
            <tfoot>
              <tr>
                <td colSpan="2" className="summary-label">号码总数</td>
                <td className="summary-value">{summary.number_count ?? summary.total_unique_numbers} 个</td>
              </tr>
              <tr>
                <td colSpan="2" className="summary-label">总金额</td>
                <td className="summary-value">{summary.total_cost} 元</td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>
    );
  }
  return null;
}

// 多条下注单窗口，每条下注单独展示结算，末尾展示全部总计（表格美化+自适应）
function MultiSettlementDetails({ details, billId }) {
  let settlementObj;
  try {
    settlementObj = typeof details === 'string' ? JSON.parse(details) : details;
  } catch {
    return <div>无法解析结算详情。</div>;
  }
  const slips = Array.isArray(settlementObj) ? settlementObj : settlementObj?.slips;
  const summary = settlementObj?.summary;
  if (!slips || slips.length === 0) {
    return <div>没有详细信息。</div>;
  }
  return (
    <div className="multi-details-container" style={{ overflowX: 'auto' }}>
      <table className="multi-slips-table">
        <thead>
          <tr>
            <th>时间/序号</th>
            <th>下注内容</th>
            <th>结算结果</th>
          </tr>
        </thead>
        <tbody>
          {slips.map((slip, idx) => (
            <tr key={idx} className="slip-row">
              <td className="slip-time">
                {slip.time ? <span className="time-tag">{slip.time}</span> : `第${slip.index}段`}
              </td>
              <td className="slip-raw">
                <pre className="slip-pre">{slip.raw}</pre>
              </td>
              <td className="slip-result">
                <SettlementDetails details={slip.result} />
              </td>
            </tr>
          ))}
        </tbody>
        {summary && (
          <tfoot>
            <tr className="summary-row">
              <td colSpan="1" className="summary-label">全部总计</td>
              <td colSpan="2" className="summary-value">
                <span>号码总数：<strong>{summary.total_number_count}</strong> 个</span>&emsp;
                <span>总金额：<strong>{summary.total_cost}</strong> 元</span>
              </td>
            </tr>
          </tfoot>
        )}
      </table>
    </div>
  );
}

// 账单详情区块（导航+原文+结算内容）
function BillDetailsViewer({ bill, onPrev, onNext, isPrevDisabled, isNextDisabled }) {
  if (!bill) {
    return <div style={{ padding: '2em', color: '#888' }}>没有账单数据。</div>;
  }
  let isMulti = false;
  try {
    const parsed = bill.settlement_details ? JSON.parse(bill.settlement_details) : null;
    isMulti = parsed && (Array.isArray(parsed?.slips) || Array.isArray(parsed));
  } catch {}
  return (
    <div className="bill-details-viewer">
      <div className="navigation-buttons">
        <button onClick={onPrev} disabled={isPrevDisabled}>&larr; 上一条</button>
        <button onClick={onNext} disabled={isNextDisabled}>下一条 &rarr;</button>
      </div>
      <div className="panels-container">
        <div className="panel" style={{minWidth: 0, flex: 1}}>
          <h3>邮件原文</h3>
          <pre className="raw-content-panel">{bill.raw_content || '无原文数据'}</pre>
        </div>
        <div className="panel" style={{minWidth: 0, flex: 1}}>
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
