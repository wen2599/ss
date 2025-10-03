import React, { useState, useEffect } from 'react';
import { updateSettlement } from '../../services/api';
import './SettlementModal.css';

/**
 * Displays the structured details of a settlement result.
 * @param {{details: object}} props
 */
const SettlementDetails = ({ details }) => {
  if (!details || !details.summary) return <div className="details-container">没有详细结算信息。</div>;
  const { number_bets = [], summary } = details;

  return (
    <div className="details-container">
      <table className="settlement-table">
        {/* Table Head */}
        <thead><tr><th>类型</th><th>内容</th><th>投注 / 中奖</th></tr></thead>
        {/* Table Body */}
        <tbody>
          {number_bets.map((bet, idx) => (
            <tr key={idx} className={bet.winnings > 0 ? 'winning-row' : ''}>
              <td>号码投注</td>
              <td>{bet.numbers?.join(' ')}</td>
              <td className="amount">
                {bet.cost} 元
                {bet.winnings > 0 && <span className="winnings-amount"> 赢: {bet.winnings} 元</span>}
              </td>
            </tr>
          ))}
        </tbody>
        {/* Table Foot */}
        <tfoot>
          <tr><td colSpan={2}>号码总数</td><td>{summary.number_count ?? 0} 个</td></tr>
          <tr><td colSpan={2}>总金额</td><td>{summary.total_cost ?? 0} 元</td></tr>
          {summary.winnings > 0 && (
            <tr><td colSpan={2} className="winning-row">总计中奖</td><td className="winning-row">{summary.winnings} 元</td></tr>
          )}
        </tfoot>
      </table>
    </div>
  );
};

/**
 * A JSON editor component with save/cancel functionality.
 * @param {{
 *   initialJson: object,
 *   onSave: (editedJson: object) => Promise<void>,
 *   onCancel: () => void
 * }} props
 */
const JsonEditor = ({ initialJson, onSave, onCancel }) => {
  const [jsonText, setJsonText] = useState(JSON.stringify(initialJson, null, 2));
  const [error, setError] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  const handleSave = async () => {
    let parsedJson;
    try {
      parsedJson = JSON.parse(jsonText);
      setError('');
    } catch (e) {
      setError(`JSON 格式错误: ${e.message}`);
      return;
    }

    setIsSaving(true);
    try {
      await onSave(parsedJson);
    } catch (e) {
      setError(`保存失败: ${e.message}`);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="json-editor-container">
      <textarea
        value={jsonText}
        onChange={(e) => setJsonText(e.target.value)}
        rows={15}
        className="json-editor-textarea"
        disabled={isSaving}
      />
      {error && <div className="save-result error">{error}</div>}
      <div className="slip-actions">
        <button onClick={handleSave} disabled={isSaving} className="action-button save">
          {isSaving ? '保存中...' : '保存'}
        </button>
        <button onClick={onCancel} disabled={isSaving} className="action-button cancel">
          取消
        </button>
      </div>
    </div>
  );
};

/**
 * Displays a single bet slip card with editing capabilities.
 * @param {{
 *  billId: number,
 *  slip: object,
 *  index: number,
 *  onSaveSuccess: () => void
 * }} props
 */
const SlipCard = ({ billId, slip, index, onSaveSuccess }) => {
    const [isEditing, setIsEditing] = useState(false);

    const handleSave = async (editedJson) => {
        await updateSettlement(billId, index, editedJson);
        setIsEditing(false);
        onSaveSuccess(); // Trigger parent to refetch data
    };

    return (
        <div className={`bet-slip-card ${isEditing ? 'editing' : ''}`}>
            <div className="slip-raw">
                <div className="slip-card-header">
                    <span className="slip-index-tag">第 {slip.index + 1} 段</span>
                    <pre className="slip-pre">{slip.raw}</pre>
                </div>
            </div>
            <div className="slip-result">
                {isEditing ? (
                    <JsonEditor initialJson={slip.result} onSave={handleSave} onCancel={() => setIsEditing(false)} />
                ) : (
                    <>
                        <SettlementDetails details={slip.result} />
                        <div className="slip-actions">
                            <button onClick={() => setIsEditing(true)} className="action-button edit">编辑结算</button>
                        </div>
                    </>
                )}
            </div>
            <div className="slip-cost">
                <span>小计</span>
                <strong>{slip.result?.summary?.total_cost || 0} 元</strong>
            </div>
        </div>
    );
};

/**
 * The main modal component for viewing and editing settlement details.
 * @param {{
 *   open: boolean,
 *   bill: object | null,
 *   onClose: () => void,
 *   onSaveSuccess: () => void
 * }} props
 */
function SettlementModal({ open, bill, onClose, onSaveSuccess }) {
  useEffect(() => {
    if (!open) {
      // Reset any state if needed when modal closes
    }
  }, [open]);

  if (!open || !bill) return null;

  const parsedDetails = typeof bill.settlement_details === 'string'
    ? JSON.parse(bill.settlement_details)
    : bill.settlement_details || { slips: [], summary: {} };

  const { slips = [], summary = {} } = parsedDetails;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content wide-modal" onClick={e => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <div className="modal-header">
          <h2>结算详情 (账单 #{bill.id})</h2>
        </div>

        {slips.length === 0 ? (
          <div className="no-slips-message">没有解析到有效的分段下注单。</div>
        ) : (
          <>
            <div className="slips-card-container">
              {slips.map((slip, index) => (
                <SlipCard key={index} billId={bill.id} slip={slip} index={index} onSaveSuccess={onSaveSuccess} />
              ))}
            </div>
            <div className="multi-details-summary">
              <strong>总计:</strong> <span>{summary.total_cost || 0} 元</span>
              <span className="summary-divider">|</span>
              <strong>总号码数:</strong> <span>{summary.total_number_count || 0} 个</span>
              {summary.total_winnings > 0 && (
                <>
                  <span className="summary-divider">|</span>
                  <strong className="winning-row">总计中奖:</strong> <span className="winning-row">{summary.total_winnings} 元</span>
                </>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

export default SettlementModal;