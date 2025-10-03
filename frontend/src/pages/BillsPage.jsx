import React, { useState, useEffect, useCallback } from 'react';
import { useAuth } from '../context/AuthContext';
import { getBills, deleteBill } from '../services/api';
import RawModal from '../components/modals/RawModal';
import SettlementModal from '../components/modals/SettlementModal';

/**
 * Renders a status badge based on the bill's status string.
 * @param {{status: string}} props
 */
const StatusBadge = ({ status }) => {
  const statusClasses = {
    processed: 'status-processed',
    settled: 'status-settled',
    unrecognized: 'status-unrecognized',
  };
  const className = `status-badge ${statusClasses[status] || 'status-default'}`;
  const statusText = {
    processed: '已处理',
    settled: '已结算',
    unrecognized: '无法识别',
  }[status] || status;

  return <span className={className}>{statusText}</span>;
};

/**
 * Renders a single row in the bills table.
 * @param {{bill: object, onSelect: () => void, onDelete: () => void, isDeleting: boolean, isSelected: boolean}} props
 */
const BillRow = ({ bill, onSelect, onDelete, isDeleting, isSelected }) => (
  <tr className={isSelected ? 'selected-row' : ''}>
    <td>{bill.id}</td>
    <td>{new Date(bill.created_at).toLocaleString()}</td>
    <td>{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
    <td><StatusBadge status={bill.status} /></td>
    <td className="action-buttons-cell">
      <button onClick={() => onSelect('raw')}>原文</button>
      <button onClick={() => onSelect('settlement')}>结算详情</button>
      <button onClick={onDelete} className="delete-button" disabled={isDeleting}>
        {isDeleting ? '正在删除...' : '删除'}
      </button>
    </td>
  </tr>
);

/**
 * A page for displaying and managing a user's bills.
 */
function BillsPage() {
  const [bills, setBills] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [deletingId, setDeletingId] = useState(null);
  const [activeModal, setActiveModal] = useState({ type: null, bill: null });
  const { isAuthenticated } = useAuth();

  const fetchBills = useCallback(async () => {
    setIsLoading(true);
    setError('');
    try {
      const data = await getBills();
      setBills(data.bills);
    } catch (err) {
      setError(err.message || '获取账单时发生未知错误。');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isAuthenticated) {
      fetchBills();
    } else {
      setBills([]);
      setIsLoading(false);
    }
  }, [isAuthenticated, fetchBills]);

  const handleDeleteBill = async (billId) => {
    if (!window.confirm(`您确定要删除账单 #${billId} 吗？此操作无法撤销。`)) return;

    setDeletingId(billId);
    try {
      await deleteBill(billId);
      setBills(prevBills => prevBills.filter(bill => bill.id !== billId));
      // If the deleted bill was open in a modal, close it.
      if (activeModal.bill?.id === billId) {
        setActiveModal({ type: null, bill: null });
      }
    } catch (err) {
      alert(`删除失败: ${err.message}`);
    } finally {
      setDeletingId(null);
    }
  };

  const handleSelectBill = (bill, modalType) => {
    setActiveModal({ type: modalType, bill });
  };

  if (!isAuthenticated) {
    return <div className="info-container">请先登录以查看您的账单。</div>;
  }
  if (isLoading) {
    return <div className="loading-container">正在加载您的账单...</div>;
  }
  if (error) {
    return <div className="error-container">{error}</div>;
  }

  return (
    <div className="bills-container">
      <h2>我的账单</h2>
      {bills.length === 0 ? (
        <p className="empty-state">您还没有任何账单记录。</p>
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
              <BillRow
                key={bill.id}
                bill={bill}
                onSelect={(modalType) => handleSelectBill(bill, modalType)}
                onDelete={() => handleDeleteBill(bill.id)}
                isDeleting={deletingId === bill.id}
                isSelected={activeModal.bill?.id === bill.id}
              />
            ))}
          </tbody>
        </table>
      )}

      <RawModal
        open={activeModal.type === 'raw'}
        rawContent={activeModal.bill?.raw_content || ''}
        onClose={() => setActiveModal({ type: null, bill: null })}
      />
      <SettlementModal
        open={activeModal.type === 'settlement'}
        bill={activeModal.bill}
        onClose={() => setActiveModal({ type: null, bill: null })}
        onSaveSuccess={fetchBills}
      />
    </div>
  );
}

export default BillsPage;