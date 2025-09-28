import React, { useState, useEffect } from 'react';
import { updateSettlement } from '../../api/client';

// This is a sub-component used only within the SettlementModal.
// It could be further broken out if it becomes more complex.
function SettlementDetails({ details }) {
  if (!details) return <div className="details-container">没有详细信息。</div>;

  let parsedDetails;
  try {
    parsedDetails = typeof details === 'string' ? JSON.parse(details) : details;
  } catch (e) {
    return <div className="details-container">无法解析详细信息。</div>;
  }

  const { zodiac_bets, number_bets, summary, settlement } = parsedDetails || {};

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


/**
 * A modal for viewing and editing the details of a bill settlement.
 * It displays betting slips in a card layout and allows editing notes.
 * @param {object} props
 * @param {boolean} props.open - Whether the modal is open.
 * @param {object} props.bill - The bill object to display.
 * @param {function} props.onClose - Function to call when the modal should close.
 * @param {function} props.onSaveSuccess - Callback to trigger when a save is successful.
 */
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
      await updateSettlement({
        billId: bill.id,
        slipIndex: editingSlipIndex,
        settlementText: editedText,
      });
      setSaveResult({ index: editingSlipIndex, message: '保存成功！' });
      onSaveSuccess(); // Callback to refresh the bills list
      setTimeout(() => {
        setEditingSlipIndex(null);
      }, 1500);
    } catch (err) {
      setSaveResult({ index: editingSlipIndex, message: `保存失败: ${err.message || '网络错误'}` });
    } finally {
      setSaving(false);
    }
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

export default SettlementModal;