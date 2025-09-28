import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

// 单条结算详情（仅用于显示）
function SettlementDetails({ details }) {
  if (!details) return <div className="details-container">没有详细信息。</div>;

  let parsedDetails;
  try {
    parsedDetails = typeof details === 'string' ? JSON.parse(details) : details;
  } catch (e) {
    return <div className="details-container">无法解析详细信息。</div>;
  }

  const { zodiac_bets, number_bets, summary, settlement } = parsedDetails || {};

  // This component should be robust enough to not crash the page.
  const safe_zodiac_bets = Array.isArray(zodiac_bets) ? zodiac_bets : [];
  const safe_number_bets = Array.isArray(number_bets) ? number_bets : [];

  if (safe_zodiac_bets.length === 0 && safe_number_bets.length === 0) {
    return <div className="details-container">没有解析到投注。</div>;
  }

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
          {safe_zodiac_bets.map((bet, idx) => (
            <tr key={`zodiac-${idx}`}>
              <td className="type-zodiac">生肖投注</td>
              <td>
                <span className="zodiac-tag">{bet.zodiac}</span>
                <span>: {bet.numbers.join(', ')}</span>
              </td>
              <td className="amount">{bet.cost} 元</td>
            </tr>
          ))}
          {safe_number_bets.map((bet, idx) => (
            <tr key={`number-${idx}`}>
              <td className="type-number">号码投注</td>
              <td>{bet.numbers.join(', ')}</td>
              <td className="amount">{bet.cost} 元</td>
            </tr>
          ))}
        </tbody>
        {summary && (
          <tfoot>
            <tr>
              <td colSpan="2" className="summary-label">号码总数</td>
              <td className="summary-value">{summary.number_count ?? 0} 个</td>
            </tr>
            <tr>
              <td colSpan="2" className="summary-label">总金额</td>
              <td className="summary-value">{summary.total_cost ?? 0} 元</td>
            </tr>
          </tfoot>
        )}
      </table>
      <div className="settlement-notes-section">
        <strong>结算备注/说明：</strong>
        <div className="readonly-notes">
          {settlement || <span className="no-notes">暂无备注</span>}
        </div>
      </div>
    </div>
  );
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

// 结算详情弹窗（卡片式布局）
function SettlementModal({ open, bill, onClose, onSaveSuccess }) {
  const [editingSlipIndex, setEditingSlipIndex] = useState(null);
  const [editedText, setEditedText] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveResult, setSaveResult] = useState({ index: null, message: '' });

  useEffect(() => {
    if (!open) {
      setEditingSlipIndex(null);
      setEditedText('');
      setSaving(false);
      setSaveResult({ index: null, message: '' });
    }
  }, [open]);

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

  const handleEditClick = (index) => {
    setEditingSlipIndex(index);
    setEditedText(slips[index]?.result?.settlement || '');
    setSaveResult({ index: null, message: '' });
  };

  const handleCancelClick = () => {
    setEditingSlipIndex(null);
    setEditedText('');
  };

  const handleSaveEdit = async () => {
    setSaving(true);
    setSaveResult({ index: editingSlipIndex, message: '' });
    try {
      const response = await fetch('/update_settlement', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          bill_id: bill.id,
          slip_index: editingSlipIndex,
          settlement_text: editedText,
        }),
        credentials: 'include'
      });
      const data = await response.json();
      if (data.success) {
        setSaveResult({ index: editingSlipIndex, message: '保存成功！' });
        onSaveSuccess(); // Callback to refresh the bills list
        setTimeout(() => {
          setEditingSlipIndex(null);
        }, 1500);
      } else {
        setSaveResult({ index: editingSlipIndex, message: `保存失败: ${data.error || '未知错误'}` });
      }
    } catch (err) {
      setSaveResult({ index: editingSlipIndex, message: '保存失败: 网络错误' });
    }
    setSaving(false);
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content wide-modal" onClick={e => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>结算详情 (账单 #{bill.id})</h2>
        {slips.length === 0 ? (
          <div className="no-slips-message">没有解析到有效的分段下注单。</div>
        ) : (
          <div className="slips-card-container">
            {slips.map((slip, index) => (
              <div key={index} className={`bet-slip-card ${editingSlipIndex === index ? 'editing' : ''}`}>
                <div className="slip-raw">
                  <div className="slip-card-header">
                    {slip.time ? <span className="time-tag">{slip.time}</span> : `第 ${slip.index} 段`}
                  </div>
                  <pre className="slip-pre">{slip.raw}</pre>
                </div>
                <div className="slip-result">
                  <SettlementDetails details={slip.result} />
                  {editingSlipIndex === index && (
                    <div className="editable-notes">
                      <textarea
                        value={editedText}
                        onChange={(e) => setEditedText(e.target.value)}
                        rows={3}
                        className="notes-textarea"
                        placeholder="可编辑结算说明..."
                        disabled={saving}
                      />
                      {saveResult.index === index && saveResult.message && (
                        <div className={`save-result ${saveResult.message.startsWith('保存成功') ? 'success' : 'error'}`}>
                          {saveResult.message}
                        </div>
                      )}
                    </div>
                  )}
                </div>
                <div className="slip-cost">
                  <span>小计</span>
                  <strong>{slip.result?.summary?.total_cost || 0} 元</strong>
                  <div className="slip-actions">
                    {editingSlipIndex === index ? (
                      <>
                        <button onClick={handleSaveEdit} disabled={saving} className="action-button save">
                          {saving ? '保存中...' : '保存'}
                        </button>
                        <button onClick={handleCancelClick} disabled={saving} className="action-button cancel">
                          取消
                        </button>
                      </>
                    ) : (
                      <button onClick={() => handleEditClick(index)} className="action-button edit">
                        编辑备注
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
            <div className="multi-details-summary">
              <strong>总计:</strong>
              <span>{summary.total_cost || 0} 元</span>
              <span className="summary-divider">|</span>
              <strong>总号码数:</strong>
              <span>{summary.total_number_count || 0} 个</span>
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
        onSaveSuccess={fetchBills}
      />
    </div>
  );
}

export default BillsPage;
