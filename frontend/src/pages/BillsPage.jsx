import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

// 单条结算详情（支持编辑备注/说明）
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
        <div style={{ marginTop: '1em' }}>
          <strong>结算备注/说明：</strong>
          {editable ? (
            <div>
              <textarea
                value={editedText}
                onChange={e => onEditChange(e.target.value)}
                rows={3}
                style={{ width: '100%', borderRadius: 8, border: '1px solid #e1e5ef', padding: 8, marginTop: 6, resize: 'vertical' }}
                placeholder="可编辑结算说明..."
                disabled={saving}
              />
              <button onClick={onSaveEdit} disabled={saving} style={{ marginTop: 8 }}>
                {saving ? '保存中...' : '保存备注'}
              </button>
              {saveResult && <div style={{ marginTop: 8, color: saveResult.startsWith('保存成功') ? '#22bb66' : '#e74c3c' }}>{saveResult}</div>}
            </div>
          ) : (
            <div style={{ background: '#f7f8fa', borderRadius: 6, padding: 6, marginTop: 6 }}>
              {settlement || <span style={{ color: '#bbb' }}>暂无备注</span>}
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
      <div className="modal-content" onClick={e => e.stopPropagation()}>
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

// 结算详情弹窗，支持上一条/下一条切换和备注编辑保存
function SettlementModal({ open, bill, onClose }) {
  if (!open || !bill) return null;
  // 解析分段
  let slips = [];
  try {
    const parsed = typeof bill.settlement_details === 'string'
      ? JSON.parse(bill.settlement_details)
      : bill.settlement_details;
    slips = parsed?.slips || [];
  } catch {
    slips = [];
  }
  const [currentIdx, setCurrentIdx] = useState(0);
  const [editText, setEditText] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveResult, setSaveResult] = useState('');

  // 初始化编辑文本
  useEffect(() => {
    setEditText(slips[currentIdx]?.result?.settlement || '');
    setSaveResult('');
    setSaving(false);
  }, [open, bill?.id, currentIdx, bill?.settlement_details]);

  if (slips.length === 0) {
    return (
      <div className="modal-overlay" onClick={onClose}>
        <div className="modal-content" onClick={e => e.stopPropagation()}>
          <button className="modal-close-button" onClick={onClose}>&times;</button>
          <h2>结算详情</h2>
          <div style={{ padding: 20, color: '#888' }}>没有分段信息。</div>
        </div>
      </div>
    );
  }

  const slip = slips[currentIdx];

  // 保存备注
  const handleSaveEdit = async () => {
    setSaving(true);
    setSaveResult('');
    try {
      const response = await fetch('/update_settlement', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          bill_id: bill.id,
          slip_index: currentIdx,
          settlement_text: editText
        }),
        credentials: 'include'
      });
      const data = await response.json();
      if (data.success) {
        setSaveResult('保存成功！');
      } else {
        setSaveResult('保存失败：' + (data.error || '未知错误'));
      }
    } catch {
      setSaveResult('保存失败：网络错误');
    }
    setSaving(false);
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" style={{ maxWidth: 600 }} onClick={e => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>结算详情</h2>
        <div className="panel" style={{ marginBottom: 0, padding: '1em', background: '#f7f8fa' }}>
          <strong>下注单原文（第{currentIdx + 1}条）{slip.time ? `【${slip.time}】` : ''}</strong>
          <pre style={{
            background: '#fff',
            border: '1px solid #e1e5ef',
            borderRadius: 8,
            padding: 10,
            marginTop: 10,
            fontFamily: 'inherit',
            fontSize: '1em',
            maxHeight: 120,
            overflow: 'auto'
          }}>{slip.raw}</pre>
        </div>
        <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 16, margin: '1em 0' }}>
          <button onClick={() => setCurrentIdx(idx => Math.max(0, idx - 1))} disabled={currentIdx === 0}>上一条</button>
          <span>第 {currentIdx + 1} / {slips.length} 条</span>
          <button onClick={() => setCurrentIdx(idx => Math.min(slips.length - 1, idx + 1))} disabled={currentIdx === slips.length - 1}>下一条</button>
        </div>
        <div className="panel" style={{ marginTop: 0, padding: '1em', background: '#f7f8fa' }}>
          <SettlementDetails
            details={slip.result}
            editable={true}
            editedText={editText}
            onEditChange={setEditText}
            onSaveEdit={handleSaveEdit}
            saving={saving}
            saveResult={saveResult}
          />
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

  const handleSelectBill = (index) => {
    setSelectedBillIndex(index);
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
                className={selectedBillIndex === index ? 'selected-row' : ''}
              >
                <td>{bill.id}</td>
                <td>{new Date(bill.created_at).toLocaleString()}</td>
                <td>{bill.total_cost ? `${bill.total_cost} 元` : 'N/A'}</td>
                <td>{renderStatus(bill.status)}</td>
                <td style={{ display: 'flex', gap: 8 }}>
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
