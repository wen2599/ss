import React, { useState, useEffect } from 'react';

function SettlementDetails({ details }) {
  if (!details) return <div className="details-container">没有详细信息。</div>;

  const { number_bets, summary } = details || {};
  const safe_number_bets = Array.isArray(number_bets) ? number_bets : [];

  if (safe_number_bets.length === 0) {
    return <div className="details-container">没有解析到投注。</div>;
  }

  return (
    <div className="details-container">
      <table className="settlement-table">
        <thead>
          <tr>
            <th>类型</th>
            <th>内容</th>
            <th>投注 / 中奖</th>
          </tr>
        </thead>
        <tbody>
          {safe_number_bets.map((bet, idx) => (
            <tr key={`number-${idx}`} className={bet.winnings > 0 ? 'winning-row' : ''}>
              <td className="type-number">号码投注</td>
              <td>
                {bet.numbers.map((num, i) => (
                  <React.Fragment key={i}>
                    <span className={bet.winning_numbers?.includes(num) ? 'winning-number' : ''}>
                      {num}
                    </span>
                    {i < bet.numbers.length - 1 && ' '}
                  </React.Fragment>
                ))}
              </td>
              <td className="amount">
                {bet.cost} 元
                {bet.winnings > 0 && (
                  <span className="winnings-amount"> 赢: {bet.winnings} 元</span>
                )}
              </td>
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
            {summary.winnings > 0 && (
              <tr>
                <td colSpan="2" className="summary-label winning-row">总计中奖</td>
                <td className="summary-value winning-row">{summary.winnings} 元</td>
              </tr>
            )}
          </tfoot>
        )}
      </table>
    </div>
  );
}

function SettlementModal({ open, bill, onClose, onSaveSuccess }) {
  const [editingSlipIndex, setEditingSlipIndex] = useState(null);
  const [editedJsonText, setEditedJsonText] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveResult, setSaveResult] = useState({ index: null, type: '', message: '' });
  const [viewMode, setViewMode] = useState('text'); // 'text' is now the default

  useEffect(() => {
    if (!open) {
      setEditingSlipIndex(null);
      setEditedJsonText('');
      setSaving(false);
      setSaveResult({ index: null, type: '', message: '' });
      setViewMode('text'); // Reset to default view on close
    }
  }, [open]);

  if (!open || !bill) return null;

  const parsedDetails = typeof bill.settlement_details === 'string'
    ? JSON.parse(bill.settlement_details)
    : bill.settlement_details || { slips: [], summary: {} };

  const slips = parsedDetails?.slips || [];
  const summary = parsedDetails?.summary || {};

  const handleEditClick = (index) => {
    setEditingSlipIndex(index);
    const resultJson = slips[index]?.result || {};
    setEditedJsonText(JSON.stringify(resultJson, null, 2));
  };

  const handleCancelClick = () => setEditingSlipIndex(null);

  const handleSaveEdit = async () => {
    let settlementResult;
    try {
      settlementResult = JSON.parse(editedJsonText);
    } catch (e) {
      setSaveResult({ index: editingSlipIndex, type: 'error', message: `JSON格式错误: ${e.message}` });
      return;
    }
    setSaving(true);
    try {
      const response = await fetch('/update_settlement', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bill_id: bill.id, slip_index: editingSlipIndex, settlement_result: settlementResult }),
        credentials: 'include'
      });
      const data = await response.json();
      if (data.success) {
        setSaveResult({ index: editingSlipIndex, type: 'success', message: '保存成功！' });
        onSaveSuccess();
        setTimeout(() => setEditingSlipIndex(null), 1500);
      } else {
        throw new Error(data.error);
      }
    } catch (err) {
      setSaveResult({ index: editingSlipIndex, type: 'error', message: `保存失败: ${err.message || '网络错误'}` });
    }
    setSaving(false);
  };

  const generateResultString = (result) => {
      if (!result || !result.summary) return '';
      const parts = [];
      if (result.number_bets && result.number_bets.length > 0) {
        result.number_bets.forEach(bet => {
          const numbersStr = bet.numbers.join(' ');
          const costPerNum = bet.cost_per_number;
          const totalCost = bet.cost;
          parts.push(`${numbersStr}各${costPerNum}元共${totalCost}元`);
        });
      }
      if (parts.length === 0) return `【总金额: ${result.summary.total_cost}元, 总号码数: ${result.summary.number_count}个】`;
      return ` 结算结果 ${parts.join(' ')} `;
  };

  const renderInjectedContent = () => {
    const { raw_content } = bill;
    if (!slips.length) return <pre className="raw-content-panel">{raw_content}</pre>;
    let lastIndex = 0;
    const contentParts = [];
    slips.forEach((slip, index) => {
      const startIndex = raw_content.indexOf(slip.raw, lastIndex);
      if (startIndex !== -1) {
        contentParts.push(raw_content.substring(lastIndex, startIndex));
        contentParts.push(slip.raw);
        const resultString = generateResultString(slip.result);
        if (resultString) contentParts.push(<span key={index} className="injected-result">{resultString}</span>);
        lastIndex = startIndex + slip.raw.length;
      }
    });
    if (lastIndex < raw_content.length) contentParts.push(raw_content.substring(lastIndex));
    return <pre className="raw-content-panel">{contentParts}</pre>;
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content wide-modal" onClick={e => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <div className="modal-header">
          <h2>结算详情 (账单 #{bill.id})</h2>
        </div>

        {viewMode === 'card' && (
          slips.length === 0 ? <div className="no-slips-message">没有解析到有效的分段下注单。</div> :
          <>
            <div className="slips-card-container">
              {slips.map((slip, index) => (
                <div key={index} className={`bet-slip-card ${editingSlipIndex === index ? 'editing' : ''}`}>
                  <div className="slip-raw">
                    <div className="slip-card-header">
                      {slip.region && <span className="region-tag">{slip.region}</span>}
                      {slip.time ? <span className="time-tag">{slip.time}</span> : `第 ${slip.index} 段`}
                    </div>
                    <pre className="slip-pre">{slip.raw}</pre>
                  </div>
                  <div className="slip-result">
                    <SettlementDetails details={slip.result} />
                    {editingSlipIndex === index && (
                      <div className="editable-notes">
                        <textarea value={editedJsonText} onChange={(e) => setEditedJsonText(e.target.value)} rows={15} className="notes-textarea json-editor" placeholder="在此编辑结算结果的JSON..." disabled={saving} />
                        {saveResult.index === index && saveResult.message && <div className={`save-result ${saveResult.type}`}>{saveResult.message}</div>}
                      </div>
                    )}
                  </div>
                  <div className="slip-cost">
                    <span>小计</span>
                    <strong>{slip.result?.summary?.total_cost || 0} 元</strong>
                    <div className="slip-actions">
                      {editingSlipIndex === index ? (
                        <>
                          <button onClick={handleSaveEdit} disabled={saving} className="action-button save">{saving ? '保存中...' : '保存'}</button>
                          <button onClick={handleCancelClick} disabled={saving} className="action-button cancel">取消</button>
                        </>
                      ) : (
                        <button onClick={() => handleEditClick(index)} className="action-button edit">编辑结算</button>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <div className="multi-details-summary">
              <strong>总计:</strong>
              <span>{summary.total_cost || 0} 元</span>
              <span className="summary-divider">|</span>
              <strong>总号码数:</strong>
              <span>{summary.total_number_count || 0} 个</span>
              {summary.total_winnings > 0 && (
                <>
                  <span className="summary-divider">|</span>
                  <strong className="winning-row">总计中奖:</strong>
                  <span className="winning-row">{summary.total_winnings} 元</span>
                </>
              )}
              {/* Display Net Result (Win/Loss) */}
              {typeof summary.net_result === 'number' && (
                  <>
                      <span className="summary-divider">|</span>
                      <strong className={summary.net_result >= 0 ? 'winning-row' : 'losing-row'}>
                          {summary.net_result >= 0 ? '净赢' : '净输'}:
                      </strong>
                      <span className={summary.net_result >= 0 ? 'winning-row' : 'losing-row'}>
                          {summary.net_result} 元
                      </span>
                  </>
              )}
            </div>
          </>
        )}

        {viewMode === 'text' && (
          <div className="panel" style={{ background: '#f7f8fa', padding: '1em', marginTop: '1em' }}>
            {renderInjectedContent()}
            <div className="multi-details-summary text-view-summary">
              <strong>总计:</strong>
              <span>{summary.total_cost || 0} 元</span>
              <span className="summary-divider">|</span>
              <strong>总号码数:</strong>
              <span>{summary.total_number_count || 0} 个</span>
              {summary.total_winnings > 0 && (
                <>
                  <span className="summary-divider">|</span>
                  <strong className="winning-row">总计中奖:</strong>
                  <span className="winning-row">{summary.total_winnings} 元</span>
                </>
              )}
              {/* Display Net Result (Win/Loss) in Text View */}
              {typeof summary.net_result === 'number' && (
                  <>
                      <span className="summary-divider">|</span>
                      <strong className={summary.net_result >= 0 ? 'winning-row' : 'losing-row'}>
                          {summary.net_result >= 0 ? '净赢' : '净输'}:
                      </strong>
                      <span className={summary.net_result >= 0 ? 'winning-row' : 'losing-row'}>
                          {summary.net_result} 元
                      </span>
                  </>
              )}
              <button onClick={() => setViewMode('card')} className="action-button edit summary-edit-button" title="切换到卡片视图进行编辑">编辑结算</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default SettlementModal;