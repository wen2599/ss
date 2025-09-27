import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

// 单条结算详情（支持编辑备注/说明，弹窗自适应，整条单编辑）
function SettlementDetails({ details, editable = false, editedText, onEditChange, onSaveEdit, saving, saveResult }) {
  if (!details) return <div className="details-container">没有详细信息。</div>;
  let parsedDetails;
  try {
    parsedDetails = typeof details === 'string' ? JSON.parse(details) : details;
  } catch (e) {
    return <div className="details-container">无法解析详细信息。</div>;
  }
  if (parsedDetails.zodiac_bets || parsedDetails.number_bets) {
    const { zodiac_bets, number_bets, summary, settlement } = parsedDetails;
    return (
      <div className="details-container">
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
              <tr>
                <td className="type-zodiac">生肖投注</td>
                <td>
                  {zodiac_bets.map(bet => (
                    <span key={bet.zodiac}>
                      <span className="zodiac-tag">{bet.zodiac}</span>：{bet.numbers.join(', ')}&nbsp;
                    </span>
                  ))}
                </td>
                <td className="amount">
                  {zodiac_bets.reduce((acc, bet) => acc + bet.cost, 0)} 元
                </td>
              </tr>
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
        <div className="settlement-notes-section">
          <strong>结算备注/说明：</strong>
          {editable ? (
            <div className="editable-notes">
              <textarea
                value={editedText}
                onChange={e => onEditChange(e.target.value)}
                rows={3}
                className="notes-textarea"
                placeholder="可编辑结算说明..."
                disabled={saving}
              />
              <button onClick={onSaveEdit} disabled={saving}>
                {saving ? '保存中...' : '保存备注'}
              </button>
              {saveResult && <div className={`save-result ${saveResult.startsWith('保存成功') ? 'success' : 'error'}`}>{saveResult}</div>}
            </div>
          ) : (
            <div className="readonly-notes">
              {settlement || <span className="no-notes">暂无备注</span>}
            </div>
          )}
        </div>
      </div>
    );
  }
  return null;
}

// 原文弹窗
function RawModal({ open, rawContent, onClose }) {
  if (!open) return null;
  return (
    <div className="modal-overlay" onClick={onClose}>
      <div
        className="modal-content"
        style={{
          maxWidth: 600,
          width: '98vw',
          minWidth: 260,
          maxHeight: '98vh',
          overflowY: 'auto',
          boxSizing: 'border-box'
        }}
        onClick={e => e.stopPropagation()}
      >
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>邮件原文</h2>
        <div className="panel" style={{ background: '#f7f8fa', padding: '1em' }}>
          <pre className="raw-content-panel" style={{ fontSize: '1em', maxHeight: 400, overflow: 'auto' }}>
            {rawContent}
          </pre>
        </div>
      </div>
    </div>
  );
}

// 结算详情弹窗（展示所有分段的表格）
function SettlementModal({ open, bill, onClose }) {
  if (!open || !bill) return null;

  let parsedDetails;
  try {
    parsedDetails = typeof bill.settlement_details === 'string'
      ? JSON.parse(bill.settlement_details)
      : bill.settlement_details;
  } catch {
    parsedDetails = { slips: [], summary: {} };
  }

  const slips = parsedDetails?.slips || [];
  const summary = parsedDetails?.summary || {};

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content wide-modal" onClick={e => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>结算详情 (账单 #{bill.id})</h2>
        {slips.length === 0 ? (
          <div className="no-slips-message">没有解析到有效的分段下注单。</div>
        ) : (
          <div className="multi-details-container">
            <table className="multi-slips-table">
              <thead>
                <tr>
                  <th>时间</th>
                  <th>下注单原文</th>
                  <th>解析结果</th>
                  <th>金额</th>
                </tr>
              </thead>
              <tbody>
                {slips.map((slip, index) => (
                  <tr key={index}>
                    <td className="slip-time">
                      {slip.time ? <span className="time-tag">{slip.time}</span> : `第 ${slip.index} 段`}
                    </td>
                    <td className="slip-raw">
                      <pre className="slip-pre">{slip.raw}</pre>
                    </td>
                    <td className="slip-result">
                      <SettlementDetails details={slip.result} />
                    </td>
                    <td className="slip-cost">
                      <strong>{slip.result?.summary?.total_cost || 0} 元</strong>
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="summary-row">
                  <td colSpan="3">总计</td>
                  <td className="summary-total-cost">
                    <strong>{summary.total_cost || 0} 元</strong>
                  </td>
                </tr>
              </tfoot>
            </table>
            <div className="multi-details-summary">
              <strong>总号码数:</strong> {summary.total_number_count || 0} 个
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

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
      />
    </div>
  );
}

export default BillsPage;
