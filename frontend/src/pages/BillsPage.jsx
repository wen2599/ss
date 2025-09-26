import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

// New component to display and manage a single slip item
function SlipItem({ slip, index, billId, onBillUpdate }) {
  const [isEditing, setIsEditing] = useState(false);
  const [settlementText, setSettlementText] = useState(slip.settlement);
  const [error, setError] = useState('');
  const [isSettling, setIsSettling] = useState(false);
  const [settleSuccess, setSettleSuccess] = useState(false);

  const handleAutoSettle = async () => {
    setError('');
    setSettleSuccess(false);
    setIsSettling(true);
    try {
      const response = await fetch('/auto_settle_slip', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          bill_id: billId,
          slip_index: index,
        }),
        credentials: 'include',
      });
      const data = await response.json();
      if (data.success) {
        setSettleSuccess(true);
        // Use a timeout to clear the success message and refresh data
        setTimeout(() => {
            setSettleSuccess(false);
            if(onBillUpdate) onBillUpdate();
        }, 1500);
      } else {
        setError(data.error || 'Failed to auto-settle.');
      }
    } catch (err) {
      setError('An error occurred during auto-settlement.');
    } finally {
      setIsSettling(false);
    }
  };

  const handleSave = async () => {
    setError('');
    try {
      const response = await fetch('/update_settlement', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          bill_id: billId,
          slip_index: index,
          settlement_text: settlementText,
        }),
        credentials: 'include',
      });
      const data = await response.json();
      if (data.success) {
        setIsEditing(false);
        // Notify parent component to refetch or update bill data
        if(onBillUpdate) onBillUpdate();
      } else {
        setError(data.error || 'Failed to save settlement.');
      }
    } catch (err) {
      setError('An error occurred while saving.');
    }
  };

  return (
    <div className="slip-item-container">
      <div className="slip-panel raw-panel">
        <h4>下注原文</h4>
        <pre>{slip.raw}</pre>
      </div>
      <div className="slip-panel settlement-panel">
        <h4>结算结果</h4>
        {isEditing ? (
          <textarea
            value={settlementText}
            onChange={(e) => setSettlementText(e.target.value)}
            rows={5}
          />
        ) : (
          <pre>{settlementText || '(无结算内容)'}</pre>
        )}
        <div className="slip-actions">
          {isEditing ? (
            <>
              <button onClick={handleSave}>保存</button>
              <button onClick={() => setIsEditing(false)} className="secondary">取消</button>
            </>
          ) : (
            <button onClick={() => setIsEditing(true)}>修改</button>
          )}
          <button onClick={handleAutoSettle} disabled={isSettling}>
            {isSettling ? '结算中...' : '自动结算'}
          </button>
        </div>
        {error && <p className="error-text">{error}</p>}
        {settleSuccess && <p className="success-text">自动结算成功！</p>}
      </div>
    </div>
  );
}

function BillDetailsViewer({ bill, onPrev, onNext, isPrevDisabled, isNextDisabled, onBillUpdate }) {
  let slips = [];
  try {
    const parsed = JSON.parse(bill.settlement_details);
    if (Array.isArray(parsed)) {
      slips = parsed;
    }
  } catch (e) {
    slips = []; // If parsing fails, start with an empty list
  }

  return (
    <div className="bill-details-viewer">
      <div className="navigation-buttons">
        <button onClick={onPrev} disabled={isPrevDisabled}>&larr; 上一条</button>
        <button onClick={onNext} disabled={isNextDisabled}>下一条 &rarr;</button>
      </div>
      <h3>账单详情 (总计 {slips.length} 条下注)</h3>
      <div className="slips-list-container">
        {slips.map((slip, index) => (
          <SlipItem
            key={index}
            slip={slip}
            index={index}
            billId={bill.id}
            onBillUpdate={onBillUpdate}
          />
        ))}
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
                <td data-label="账单ID">{bill.id}</td>
                <td data-label="创建时间">{new Date(bill.created_at).toLocaleString()}</td>
                <td data-label="总金额">{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
                <td data-label="状态">{renderStatus(bill.status)}</td>
                <td data-label="操作">
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
          onBillUpdate={fetchBills}
        />
      )}
    </div>
  );
}

export default BillsPage;
