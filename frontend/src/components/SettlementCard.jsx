import React, { useState, useMemo } from 'react';
import { apiService } from '../api';

/**
 * SettlementCard 组件 - 增强版
 * 显示详细的结算信息并提供编辑功能
 */
const SettlementCard = ({ batch, lotteryResult, onUpdate }) => {
  const { batch_id, data, settlement, ai_model } = batch;
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));
  const [isSaving, setIsSaving] = useState(false);

  // 结算计算
  const { totalBetAmount, winningBets, summaryText } = useMemo(() => {
    if (settlement) {
      return {
        totalBetAmount: settlement.total_bet_amount,
        winningBets: settlement.winning_details,
        summaryText: settlement.summary
      };
    }

    // 如果没有结算数据，使用前端计算
    let total = 0;
    const winners = [];
    const specialNumber = lotteryResult?.winning_numbers[6];
    const betSummary = {};

    if (Array.isArray(data.bets)) {
      data.bets.forEach(bet => {
        const amount = Number(bet.amount) || 0;
        if ((bet.bet_type === '号码' || bet.bet_type === '特码') && Array.isArray(bet.targets)) {
          bet.targets.forEach(targetNumber => {
            total += amount;
            betSummary[amount] = (betSummary[amount] || 0) + 1;

            if (lotteryResult && specialNumber && String(targetNumber).trim() === String(specialNumber).trim()) {
              winners.push({ number: targetNumber, amount: amount });
            }
          });
        }
      });
    }

    const summaryParts = Object.entries(betSummary).map(([amount, count]) => `${amount}元x${count}个`);
    const summary = `总下注 ${total} 元 (${summaryParts.join(', ')})`;

    return { totalBetAmount: total, winningBets: winners, summaryText: summary };
  }, [data.bets, lotteryResult, settlement]);

  // 保存修改
  const handleSave = async () => {
    setIsSaving(true);
    try {
      const updatedBets = JSON.parse(editableData);
      if (!Array.isArray(updatedBets)) {
        throw new Error("JSON 格式必须是一个数组 [...]");
      }

      const updatedBatchData = { ...data, bets: updatedBets };
      await apiService.updateBetBatch(batch_id, updatedBatchData);
      onUpdate(batch_id, updatedBatchData);
      setIsEditing(false);
    } catch (e) {
      alert("JSON 格式错误或保存失败: " + e.message);
    } finally {
      setIsSaving(false);
    }
  };

  // 渲染中奖详情
  const renderWinningDetails = (odds) => {
    if (!lotteryResult) {
      return <span style={{ color: '#666' }}>等待开奖号码...</span>;
    }
    if (winningBets.length === 0) {
      return (
        <span style={{ fontWeight: 'bold', color: 'green' }}>
          未中奖 | 净亏 {totalBetAmount} 元
        </span>
      );
    }

    const totalWinAmount = winningBets.reduce((sum, bet) => sum + (bet.amount * odds), 0);
    const netProfit = totalWinAmount - totalBetAmount;

    return (
      <>
        <span style={{ color: 'blue', fontWeight: 'bold' }}>
          中 {winningBets.length} 注, 赢 {totalWinAmount}元
        </span>{' '}
        |{' '}
        <span style={{ fontWeight: 'bold', color: netProfit >= 0 ? 'red' : 'green' }}>
          净{netProfit >= 0 ? '赢' : '亏'} {Math.abs(netProfit)} 元
        </span>
      </>
    );
  };

  return (
    <div className="settlement-card" style={{ 
      border: '2px solid #e3f2fd',
      borderRadius: '8px',
      margin: '1rem 0',
      padding: '1rem',
      backgroundColor: '#f8fdff'
    }}>
      {/* 批次头部信息 */}
      <div style={{ 
        display: 'flex', 
        justifyContent: 'space-between', 
        alignItems: 'center',
        marginBottom: '1rem',
        paddingBottom: '0.5rem',
        borderBottom: '1px solid #e0e0e0'
      }}>
        <div>
          <strong>批次 ID: {batch_id}</strong>
          <span style={{ marginLeft: '1rem', color: '#666', fontSize: '0.9rem' }}>
            AI模型: {ai_model}
          </span>
        </div>
        <button 
          onClick={() => setIsEditing(!isEditing)}
          style={{
            padding: '0.25rem 0.5rem',
            backgroundColor: isEditing ? '#dc3545' : '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer',
            fontSize: '0.8rem'
          }}
        >
          {isEditing ? '取消修改' : '修改识别'}
        </button>
      </div>

      {/* 原始文本 */}
      <div style={{ 
        whiteSpace: 'pre-wrap',
        backgroundColor: '#f5f5f5',
        padding: '0.5rem',
        borderRadius: '4px',
        marginBottom: '1rem',
        fontFamily: 'monospace',
        fontSize: '0.9rem'
      }}>
        {data.raw_text}
      </div>

      {/* 结算详情 */}
      <div className="settlement-details">
        {/* AI 识别概要 */}
        <div style={{ 
          backgroundColor: '#fff3cd',
          border: '1px solid #ffeaa7',
          padding: '0.75rem',
          borderRadius: '4px',
          marginBottom: '1rem'
        }}>
          <p style={{ 
            color: '#856404', 
            fontSize: '1.1rem', 
            margin: '0.5rem 0', 
            fontWeight: 'bold' 
          }}>
            🎯 AI识别概要: {summaryText}
          </p>
        </div>

        {/* 结算结果 */}
        <div className="results-grid">
          <div style={{ marginBottom: '0.5rem' }}>
            <strong>中奖详情:</strong>{' '}
            {winningBets.length > 0 ? (
              <span style={{ color: 'blue' }}>
                {winningBets.map(b => `${b.number}(${b.amount}元)`).join(', ')}
              </span>
            ) : (
              <span style={{ color: 'green' }}>无</span>
            )}
          </div>
          
          <div style={{ marginBottom: '0.5rem' }}>
            <strong>赔率 45:</strong> {renderWinningDetails(45)}
          </div>
          <div style={{ marginBottom: '0.5rem' }}>
            <strong>赔率 46:</strong> {renderWinningDetails(46)}
          </div>
          <div style={{ marginBottom: '0.5rem' }}>
            <strong>赔率 47:</strong> {renderWinningDetails(47)}
          </div>
        </div>
      </div>

      {/* 编辑模式 */}
      {isEditing && (
        <div style={{ 
          marginTop: '1rem',
          padding: '1rem',
          backgroundColor: '#f8f9fa',
          border: '1px solid #dee2e6',
          borderRadius: '4px'
        }}>
          <p style={{ fontSize: '0.9rem', color: '#666', margin: '0 0 0.5rem 0' }}>
            请直接编辑以下代表下注内容的 JSON 数据：
          </p>
          <textarea
            value={editableData}
            onChange={(e) => setEditableData(e.target.value)}
            style={{
              width: '98%',
              height: '200px',
              fontFamily: 'monospace',
              fontSize: '0.9rem',
              border: '1px solid #ccc',
              padding: '8px',
              borderRadius: '4px'
            }}
          />
          <div style={{ marginTop: '0.5rem' }}>
            <button 
              onClick={handleSave} 
              disabled={isSaving}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#28a745',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
                marginRight: '0.5rem'
              }}
            >
              {isSaving ? '保存中...' : '保存修改'}
            </button>
            <button 
              onClick={() => setIsEditing(false)}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#6c757d',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer'
              }}
            >
              取消
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default SettlementCard;