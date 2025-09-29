import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import SettlementModal from '../components/modals/SettlementModal';

// A new component to render settlement details directly on the page.
function SettlementDetailsView({ details }) {
  if (!details) {
    return <div className="details-container">没有详细信息。</div>;
  }
  const { slips, summary } = details;

  if (!slips || slips.length === 0) {
    return <div className="details-container">没有解析到投注。</div>;
  }

  return (
    <div className="settlement-details-view">
      {slips.map((slip, index) => (
        <div key={index} className="mini-slip-card">
          <div className="mini-slip-header">
            <strong>{slip.region || `第 ${slip.index} 段`}</strong>
            {slip.time && <span className="time-tag">{slip.time}</span>}
          </div>
          <pre className="mini-slip-raw">{slip.raw}</pre>
          {slip.result.summary.winnings > 0 && (
            <div className="mini-slip-winnings">
              中奖: {slip.result.summary.winnings} 元
            </div>
          )}
        </div>
      ))}
      <div className="multi-details-summary">
        <strong>总计:</strong>
        <span>{summary.total_cost || 0} 元</span>
        <span className="summary-divider">|</span>
        <strong className={summary.net_result >= 0 ? 'winning-row' : 'losing-row'}>
          {summary.net_result >= 0 ? '净赢' : '净输'}:
        </strong>
        <span className={summary.net_result >= 0 ? 'winning-row' : 'losing-row'}>
          {summary.net_result} 元
        </span>
      </div>
    </div>
  );
}

function BillsPage() {
  const [bills, setBills] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedBillIndex, setSelectedBillIndex] = useState(null);
  const [showSettlementModal, setShowSettlementModal] = useState(false);
  const { user, isAuthenticated } = useAuth();

  const fetchBills = async () => {
    setIsLoading(true);
    try {
      // Using an explicit action parameter to ensure correct routing on the backend.
      const response = await fetch('/?action=get_bills', { credentials: 'include' });
      const data = await response.json();
      if (data.success) {
        setBills(data.bills);
      } else {
        setError(data.error || 'Failed to fetch bills.');
      }
    } catch (err) {
      setError('An error occurred while fetching bills.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (isAuthenticated) fetchBills();
    else setBills([]);
  }, [isAuthenticated, user]);

  const handleDeleteBill = async (billId) => {
    if (!window.confirm(`您确定要删除账单 #${billId} 吗？此操作无法撤销。`)) return;
    try {
      const response = await fetch('/?action=delete_bill', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bill_id: billId }),
        credentials: 'include'
      });
      const data = await response.json();
      if (data.success) {
        fetchBills(); // Refetch to update the list
        setSelectedBillIndex(null); // Close details view
      } else {
        alert(`删除失败: ${data.error}`);
      }
    } catch (err) {
      alert('删除时发生错误。');
    }
  };

  const renderStatus = (status) => {
    switch (status) {
      case 'processed': return <span className="status-processed">已处理</span>;
      case 'settled': return <span className="status-settled">已结算</span>;
      case 'unrecognized': return <span className="status-unrecognized">无法识别</span>;
      default: return <span className="status-default">{status}</span>;
    }
  };

  const toggleBillSelection = (index) => {
    setSelectedBillIndex(selectedBillIndex === index ? null : index);
  };

  if (isLoading) return <div>正在加载您的账单...</div>;
  if (error) return <div className="error">{error}</div>;

  const selectedBill = selectedBillIndex !== null ? bills[selectedBillIndex] : null;
  const selectedBillDetails = selectedBill && selectedBill.settlement_details
    ? JSON.parse(selectedBill.settlement_details)
    : null;

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
            {bills.map((bill, index) => (
              <React.Fragment key={bill.id}>
                <tr className={`bill-summary-row ${selectedBillIndex === index ? 'selected-row' : ''}`} onClick={() => toggleBillSelection(index)}>
                  <td>{bill.id}</td>
                  <td>{new Date(bill.created_at).toLocaleString()}</td>
                  <td>{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
                  <td>{renderStatus(bill.status)}</td>
                </tr>
                {selectedBillIndex === index && (
                  <tr className="bill-details-row">
                    <td colSpan="4">
                      <div className="bill-details-container">
                        <div className="details-tile raw-content-tile">
                          <h4>原文</h4>
                          <pre className="raw-content-display">{bill.raw_content}</pre>
                        </div>
                        <div className="details-tile settlement-details-tile">
                          <h4>结算详情</h4>
                          <SettlementDetailsView details={selectedBillDetails} />
                        </div>
                        <div className="details-tile actions-tile">
                          <h4>操作</h4>
                          <button onClick={() => setShowSettlementModal(true)}>编辑结算</button>
                          <button onClick={() => handleDeleteBill(bill.id)} className="delete-button">删除账单</button>
                        </div>
                      </div>
                    </td>
                  </tr>
                )}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      )}
      {selectedBill && (
        <SettlementModal
          open={showSettlementModal}
          bill={selectedBill}
          onClose={() => setShowSettlementModal(false)}
          onSaveSuccess={() => {
            setShowSettlementModal(false);
            fetchBills();
          }}
        />
      )}
    </div>
  );
}

export default BillsPage;