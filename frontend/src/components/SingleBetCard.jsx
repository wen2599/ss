// File: frontend/src/components/SingleBetCard.jsx (修复快速校准功能)
import React, { useState, useMemo } from 'react';
import { apiService } from '../api';
import QuickCalibrationModal from './QuickCalibrationModal';

function SingleBetCard({ lineData, emailId, onUpdate, onDelete }) {
  const [isParsing, setIsParsing] = useState(false);
  const [showLotteryModal, setShowLotteryModal] = useState(false);
  const [showCalibrationModal, setShowCalibrationModal] = useState(false);

  const handleParse = () => setShowLotteryModal(true);

  const handleConfirmParse = async (lotteryTypes) => {
    setIsParsing(true);
    setShowLotteryModal(false);
    try {
      const result = await apiService.parseSingleBet(
        emailId, // 直接使用数字类型的 emailId
        lineData.text,
        lineData.line_number,
        lotteryTypes[0]
      );
      if (result.status === 'success') onUpdate(lineData.line_number, result.data);
      else alert('解析失败: ' + (result.message || '未知错误'));
    } catch (error) {
      alert('解析失败: ' + error.message);
    } finally {
      setIsParsing(false);
    }
  };

  const handleLineUpdate = (lineNumber, data) => {
    onUpdate(lineNumber, data);
  };

  const formatTargets = (targets) => {
    if (!Array.isArray(targets)) return String(targets || '');
    if (targets.every(t => !isNaN(t))) return targets.map(n => String(n).padStart(2, '0')).join('.');
    return targets.join(', ');
  };

  const aggregatedBets = useMemo(() => {
    if (!lineData.is_parsed || !lineData.batch_data?.data?.bets) return [];
    const groups = {};
    lineData.batch_data.data.bets.forEach(bet => {
      const key = `${bet.bet_type}_${bet.amount}`;
      if (!groups[key]) groups[key] = { ...bet, targets: [] };
      if (Array.isArray(bet.targets)) groups[key].targets.push(...bet.targets);
    });
    return Object.values(groups);
  }, [lineData.batch_data]);

  const displayTotalAmount = lineData.batch_data?.data?.settlement?.total_bet_amount ?? lineData.batch_data?.data?.total_amount ?? 0;

  return (
    <>
      <div style={{ border: '1px solid #e0e0e0', borderRadius: '8px', padding: '1rem', marginBottom: '1rem', backgroundColor: lineData.is_parsed ? '#f8fdff' : '#f9f9f9' }}>
        <div style={{ display: 'inline-block', backgroundColor: lineData.is_parsed ? '#28a745' : '#6c757d', color: 'white', borderRadius: '12px', padding: '0.25rem 0.5rem', fontSize: '0.8rem', marginBottom: '0.5rem' }}>
          第 {lineData.line_number} 条 {lineData.is_parsed ? '✅ 已解析' : '❌ 未解析'}
        </div>
        <div style={{ backgroundColor: '#f5f5f5', padding: '0.75rem', borderRadius: '4px', marginBottom: '1rem', fontFamily: 'monospace', fontSize: '0.9rem', whiteSpace: 'pre-wrap' }}>
          {lineData.text}
        </div>
        <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
          {!lineData.is_parsed ? (
            <button onClick={handleParse} disabled={isParsing} style={{ backgroundColor: isParsing ? '#6c757d' : '#28a745', color: 'white', border: 'none', borderRadius: '4px', padding: '0.5rem 1rem' }}>
              {isParsing ? '解析中...' : '解析此条'}
            </button>
          ) : (
            <>
              <button onClick={() => setShowCalibrationModal(true)} style={{ backgroundColor: '#ffc107', color: '#212529', border: 'none', borderRadius: '4px', padding: '0.5rem 1rem' }}>
                校准金额
              </button>
              <button onClick={() => { if (window.confirm('确定要删除这条解析结果吗？')) { onDelete(lineData.line_number); } }} style={{ backgroundColor: '#dc3545', color: 'white', border: 'none', borderRadius: '4px', padding: '0.5rem 1rem' }}>
                删除解析
              </button>
            </>
          )}
        </div>
        {lineData.is_parsed && lineData.batch_data && (
          <div style={{ marginTop: '1rem' }}>
            <div style={{ backgroundColor: '#e8f5e8', border: '1px solid #4caf50', padding: '0.75rem', borderRadius: '4px' }}>
              <h4 style={{ margin: '0 0 0.5rem 0', color: '#2e7d32' }}>✅ AI解析结果</h4>
              {lineData.batch_data.data.lottery_type && <div style={{ marginBottom: '1rem', padding: '0.5rem', backgroundColor: '#d4edda', borderRadius: '4px', display: 'inline-block' }}><strong>彩票类型:</strong> {lineData.batch_data.data.lottery_type}</div>}
              <div style={{ marginBottom: '1rem' }}>
                {aggregatedBets.map((bet, index) => (
                  <div key={index} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem', padding: '0.75rem', backgroundColor: 'white', borderRadius: '6px', border: '1px solid #dee2e6' }}>
                    <div style={{ flex: 1 }}>
                      <div style={{ fontWeight: 'bold', fontSize: '1rem' }}>{bet.bet_type} ({Array.isArray(bet.targets) ? bet.targets.length : 0}个)</div>
                      <div style={{ fontFamily: 'monospace', wordBreak: 'break-word', backgroundColor: '#f8f9fa', padding: '0.5rem', borderRadius: '4px', marginTop: '0.5rem' }}>{formatTargets(bet.targets)}</div>
                    </div>
                    <div style={{ textAlign: 'right', minWidth: '100px', marginLeft: '0.5rem' }}>
                      <div style={{ fontSize: '1rem', fontWeight: 'bold', color: '#e74c3c' }}>{bet.amount} 元</div>
                      <div style={{ fontSize: '0.8rem', color: '#7f8c8d' }}>{['特码', '号码', '平码'].includes(bet.bet_type) ? '每个' : '总共'}</div>
                    </div>
                  </div>
                ))}
              </div>
              {lineData.batch_data.data.settlement && (
                <div style={{ marginTop: '1rem', padding: '1rem', backgroundColor: '#fff3cd', borderRadius: '8px', border: '1px solid #ffeaa7' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-around', alignItems: 'center', marginBottom: '1rem', textAlign: 'center' }}>
                    <div>
                      <div style={{ fontSize: '0.9rem', color: '#6c757d' }}>总下注</div>
                      <div style={{ fontSize: '1.25rem', fontWeight: 'bold' }}>{displayTotalAmount} 元</div>
                    </div>
                    <div>
                      <div style={{ fontSize: '0.9rem', color: '#6c757d' }}>中奖注数</div>
                      <div style={{ fontSize: '1.25rem', fontWeight: 'bold' }}>{lineData.batch_data.data.settlement.winning_details?.length || 0}</div>
                    </div>
                  </div>
                  {lineData.batch_data.data.settlement.net_profits && (
                    <div style={{ padding: '0.75rem', backgroundColor: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#d4edda' : '#f8d7da', borderRadius: '6px', textAlign: 'center', fontWeight: 'bold', fontSize: '1.1rem', color: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#155724' : '#721c24' }}>
                      {lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '🎉 盈利' : '📉 亏损'}
                      <span style={{ fontSize: '1.25rem', marginLeft: '0.5rem' }}>{lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '+' : ''}{lineData.batch_data.data.settlement.net_profits.net_profit} 元</span>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* 快速校准模态框 */}
      <QuickCalibrationModal
        isOpen={showCalibrationModal}
        onClose={() => setShowCalibrationModal(false)}
        lineData={lineData}
        emailId={Number(emailId)}
        onUpdate={handleLineUpdate}
      />

      <LotteryTypeModal
        isOpen={showLotteryModal}
        onClose={() => setShowLotteryModal(false)}
        onConfirm={handleConfirmParse}
        loading={isParsing}
      />
    </>
  );
}

function LotteryTypeModal({ isOpen, onClose, onConfirm, loading }) {
    const [selectedTypes, setSelectedTypes] = useState([]);
    const lotteryTypes = [
      { value: '香港六合彩', label: '香港六合彩 (周二、四、六开奖)' },
      { value: '新澳门六合彩', label: '新澳门六合彩 (每日开奖)' },
      { value: '老澳门六合彩', label: '老澳门六合彩 (每日开奖)' }
    ];
    const handleTypeToggle = (type) => { setSelectedTypes([type]); };
    const handleConfirm = () => {
      if (selectedTypes.length === 0) {
        alert('请选择一种彩票类型');
        return;
      }
      onConfirm(selectedTypes);
    };

    if (!isOpen) return null;

    return (
      <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
        <div style={{ backgroundColor: 'white', padding: '2rem', borderRadius: '12px', minWidth: '400px', maxWidth: '500px' }}>
          <h3>选择彩票类型</h3>
          <div style={{ marginBottom: '1.5rem' }}>
            {lotteryTypes.map(type => (
              <label key={type.value} style={{ display: 'block', marginBottom: '0.5rem' }}>
                <input
                  type="radio"
                  name="lotteryType"
                  checked={selectedTypes.includes(type.value)}
                  onChange={() => handleTypeToggle(type.value)}
                  style={{ marginRight: '0.5rem' }}
                />
                {type.label}
              </label>
            ))}
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem' }}>
            <button onClick={onClose} disabled={loading} style={{ padding: '0.5rem 1rem', backgroundColor: '#6c757d', color: 'white', border: 'none', borderRadius: '4px' }}>
              取消
            </button>
            <button
              onClick={handleConfirm}
              disabled={loading || selectedTypes.length === 0}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: (loading || selectedTypes.length === 0) ? '#6c757d' : '#007bff',
                color: 'white',
                border: 'none',
                borderRadius: '4px'
              }}
            >
              {loading ? '解析中...' : '开始解析'}
            </button>
          </div>
        </div>
      </div>
    );
}

export default SingleBetCard;