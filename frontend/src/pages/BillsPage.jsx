import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { getBills, deleteBill } from '../api/client';
import RawModal from '../components/modals/RawModal';
import SettlementModal from '../components/modals/SettlementModal';

function BillsPage() {
  const [bills, setBills] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedBillIndex, setSelectedBillIndex] = useState(null);
  const [showRawModal, setShowRawModal] = useState(false);
  const [showSettlementModal, setShowSettlementModal] = useState(false);
  const { user, isAuthenticated } = useAuth();

  const fetchBills = async () => {
    setIsLoading(true);
    setError('');
    try {
      const userBills = await getBills();
      setBills(userBills);
    } catch (err) {
      setError(err.message || 'An error occurred while fetching bills.');
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

  const handleDeleteBill = async (billId) => {
    if (!window.confirm(`您确定要删除账单 #${billId} 吗？此操作无法撤销。`)) {
      return;
    }
    try {
      await deleteBill(billId);
      setBills(prevBills => prevBills.filter(bill => bill.id !== billId));
      if (selectedBillIndex !== null && bills[selectedBillIndex]?.id === billId) {
        setSelectedBillIndex(null);
      }
    } catch (err) {
      alert(`删除失败: ${err.message}`);
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
                className={selectedBillIndex === index ? 'selected-row' : ''}
              >
                <td>{bill.id}</td>
                <td>{new Date(bill.created_at).toLocaleString()}</td>
                <td>{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
                <td>{renderStatus(bill.status)}</td>
                <td className="action-buttons-cell">
                  <button
                    onClick={e => { e.stopPropagation(); setSelectedBillIndex(index); setShowRawModal(true); }}
                  >原文</button>
                  <button
                    onClick={e => { e.stopPropagation(); setSelectedBillIndex(index); setShowSettlementModal(true); }}
                  >结算详情</button>
                  <button
                    onClick={e => { e.stopPropagation(); handleDeleteBill(bill.id); }}
                    className="delete-button"
                  >删除</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <RawModal
        open={showRawModal && selectedBill}
        rawContent={selectedBill ? selectedBill.raw_content : ''}
        onClose={() => setShowRawModal(false)}
      />
      <SettlementModal
        open={showSettlementModal && selectedBill}
        bill={selectedBill}
        onClose={() => setShowSettlementModal(false)}
        onSaveSuccess={fetchBills}
      />
    </div>
  );
}

export default BillsPage;
