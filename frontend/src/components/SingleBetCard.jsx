// File: frontend/src/components/SingleBetCard.jsx (修复 emailId 传递问题)
import React, { useState, useMemo } from 'react';
import { apiService } from '../api'; // 确保 apiService 被引入
import AiCalibrationModal from './AiCalibrationModal';

function SingleBetCard({ lineData, emailId, onUpdate, onDelete, showParseButton = true }) {
  const [isParsing, setIsParsing] = useState(false);
  const [showLotteryModal, setShowLotteryModal] = useState(false);
  const [showCalibrationModal, setShowCalibrationModal] = useState(false);

  const handleParse = () => {
    setShowLotteryModal(true);
  };

  const handleConfirmParse = async (lotteryTypes) => {
    setIsParsing(true);
    setShowLotteryModal(false);
    try {
      const result = await apiService.parseSingleBet(
        parseInt(emailId, 10),
        lineData.text,
        lineData.line_number,
        lotteryTypes[0]
      );
      if (result.status === 'success') {
        onUpdate(lineData.line_number, result.data);
      } else {
        alert('解析失败: ' + (result.message || '未知错误'));
      }
    } catch (error) {
      alert('解析失败: ' + error.message);
    } finally {
      setIsParsing(false);
    }
  };

  const formatTargets = (targets) => {
    if (!Array.isArray(targets)) return String(targets || '');
    if (targets.every(target => !isNaN(target))) {
      return targets.map(num => String(num).padStart(2, '0')).join('.');
    }
    return targets.join(', ');
  };

  const getTargetCount = (targets) => {
    if (!Array.isArray(targets)) return 1;
    return targets.length;
  };
  
  const aggregatedBets = useMemo(() => {
    if (!lineData.is_parsed || !lineData.batch_data?.data?.bets) return [];
    const groups = {};
    lineData.batch_data.data.bets.forEach(bet => {
      const key = `${bet.bet_type}_${bet.amount}`;
      if (!groups[key]) {
        groups[key] = { ...bet, targets: [] };
      }
      if (Array.isArray(bet.targets)) {
          groups[key].targets.push(...bet.targets);
      }
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
            <button onClick={handleParse} disabled={isParsing} style={{ backgroundColor: isParsing ? '#6c757d' : '#28a745' }}>
              {isParsing ? '解析中...' : '解析此条'}
            </button>
          ) : (
            <>
              <button onClick={() => setShowCalibrationModal(true)} style={{ backgroundColor: '#ffc107', color: '#212529' }}>
                校准解析
              </button>
              <button onClick={() => { if (window.confirm('确定要删除这条解析结果吗？')) { onDelete(lineData.line_number); } }} style={{ backgroundColor: '#dc3545' }}>
                删除解析
              </button>
            </>
          )}
        </div>

        {lineData.is_parsed && lineData.batch_data && (
          <div style={{ marginTop: '1rem' }}>
            <div style={{ backgroundColor: '#e8f5e8', border: '1px solid #4caf50', padding: '0.75rem', borderRadius: '4px' }}>
              <h4 style={{ margin: '0 0 0.5rem 0', color: '#2e7d32' }}>✅ 解析结果</h4>
              {lineData.batch_data.data.lottery_type && (
                <div style={{ marginBottom: '1rem', padding: '0.5rem', backgroundColor: '#d4edda', borderRadius: '4px', display: 'inline-block' }}>
                  <strong>彩票类型:</strong> {lineData.batch_data.data.lottery_type}
                </div>
              )}
              
              <div style={{ marginBottom: '1rem' }}>
                {aggregatedBets.map((bet, index) => {
                  const targetCount = getTargetCount(bet.targets);
                  const isNumberBet = ['特码', '号码', '平码'].includes(bet.bet_type);
                  return (
                    <div key={index} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem', padding: '0.75rem', backgroundColor: 'white', borderRadius: '6px', border: '1px solid #dee2e6' }}>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 'bold', fontSize: '1rem' }}>{bet.bet_type} ({targetCount}个)</div>
                        <div style={{ fontFamily: 'monospace', wordBreak: 'break-word', backgroundColor: '#f8f9fa', padding: '0.5rem', borderRadius: '4px', marginTop: '0.5rem' }}>
                          {formatTargets(bet.targets)}
                        </div>
                      </div>
                      <div style={{ textAlign: 'right', minWidth: '100px', marginLeft: '0.5rem' }}>
                        <div style={{ fontSize: '1rem', fontWeight: 'bold', color: '#e74c3c' }}>{bet.amount} 元</div>
                        <div style={{ fontSize: '0.8rem', color: '#7f8c8d' }}>{isNumberBet ? '每个' : '总共'}</div>
                      </div>
                    </div>
                  );
                })}
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
                    <div style={{ padding: '0.75rem', backgroundColor: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#d4edda' : '#f8d7da', borderRadius: '6px', textAlign: 'center', fontWeight: 'bold', fontSize: '1.1rem', color: lineData.batch_data.data.settlement.net_profits.net_profit >=.
