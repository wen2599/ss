import React, { useState, useMemo } from 'react';
import { apiService } from '../api';

/**
 * SettlementCard 组件 - 修复版
 * 显示详细的结算信息并提供编辑功能
 */
const SettlementCard = ({ batch, lotteryResult, onUpdate }) => {
  const { batch_id, data, settlement, ai_model } = batch;
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));
  const [isSaving, setIsSaving] = useState(false);

  // 结算计算 - 修复版
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
    const betSummary = {};

    if (Array.isArray(data.bets)) {
      data.bets.forEach(bet => {
        const amount = Number(bet.amount) || 0;
        if ((bet.bet_type === '号码' || bet.bet_type === '特码' || bet.bet_type === '平码') && Array.isArray(bet.targets)) {
          bet.targets.forEach(targetNumber => {
            total += amount;
            betSummary[amount] = (betSummary[amount] || 0) + 1;

            // 如果有开奖结果，进行实际结算
            if (lotteryResult && Array.isArray(lotteryResult.winning_numbers)) {
              // 特码玩法：只对比特码（最后一个号码）
              if (bet.bet_type === '特码' || bet.bet_type === '号码') {
                const specialNumber = lotteryResult.winning_numbers[lotteryResult.winning_numbers.length - 1];
                if (String(targetNumber).trim() === String(specialNumber).trim()) {
                  winners.push({ 
                    number: targetNumber, 
                    amount: amount,
                    bet_type: bet.bet_type
                  });
                }
              }
              // 平码玩法：对比所有号码
              else if (bet.bet_type === '平码') {
                if (lotteryResult.winning_numbers.includes(String(targetNumber).trim())) {
                  winners.push({ 
                    number: targetNumber, 
                    amount: amount,
                    bet_type: bet.bet_type
                  });
                }
              }
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

  // 渲染中奖详情 - 修复版
  const renderWinningDetails = (odds) => {
    // 如果没有开奖结果，显示提示信息
    if (!lotteryResult || !Array.isArray(lotteryResult.winning_numbers)) {
      return (
        <div style={{ color: '#666', fontStyle: 'italic' }}>
          暂无开奖数据，无法计算中奖情况
          {lotteryResult && (
            <div style={{ fontSize: '0.8rem', marginTop: '0.25rem' }}>
              最新开奖期号: {lotteryResult.issue_number}
            </div>
          )}
        </div>
      );
    }

    // 如果有开奖结果但未中奖
    if (winningBets.length === 0) {
      const totalWinAmount = 0;
      const netProfit = -totalBetAmount;

      return (
        <>
          <span style={{ color: 'green', fontWeight: 'bold' }}>
            未中奖
          </span>{' '}
          |{' '}
          <span style={{ fontWeight: 'bold', color: netProfit >= 0 ? 'red' : 'blue' }}>
            净亏 {Math.abs(netProfit)} 元
          </span>
        </>
      );
    }

    // 计算中奖金额
    const totalWinAmount = winningBets.reduce((sum, bet) => sum + (bet.amount * odds), 0);
    const netProfit = totalWinAmount - totalBetAmount;

    return (
      <>
        <span style={{ color: 'red', fontWeight: 'bold' }}>
          中 {winningBets.length} 注, 赢 {totalWinAmount}元
        </span>{' '}
        |{' '}
        <span style={{ fontWeight: 'bold', color: netProfit >= 0 ? 'red' : 'blue' }}>
          净{netProfit >= 0 ? '赢' : '亏'} {Math.abs(netProfit)} 元
        </span>
      </>
    );
  };

  // 获取特码号码
  const getSpecialNumber = () => {
    if (!lotteryResult || !Array.isArray(lotteryResult.winning_numbers)) {
      return '暂无';
    }
    return lotteryResult.winning_numbers[lotteryResult.winning_numbers.length - 1];
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

      {/* 开奖信息 */}
      {lotteryResult && (
        <div style={{
          backgroundColor: '#e8f5e8',
          border: '1px solid #4caf50',
          padding: '0.75rem',
          borderRadius: '4px',
          marginBottom: '1rem'
        }}>
          <p style={{ margin: '0 0 0.5rem 0', fontWeight: 'bold', color: '#2e7d32' }}>
            🎯 开奖信息: {lotteryResult.lottery_type} 第 {lotteryResult.issue_number} 期
          </p>
          <p style={{ margin: '0.25rem 0', fontSize: '0.9rem' }}>
            <strong>开奖号码:</strong> {lotteryResult.winning_numbers?.join(', ') || '暂无'}
          </p>
          <p style={{ margin: '0.25rem 0', fontSize: '0.9rem' }}>
            <strong>特码:</strong> <span style={{ color: 'red', fontWeight: 'bold' }}>{getSpecialNumber()}</span>
          </p>
        </div>
      )}

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
              <span style={{ color: 'red', fontWeight: 'bold' }}>
                {winningBets.map(b => `${b.number}(${b.amount}元)`).join(', ')}
              </span>
            ) : lotteryResult ? (
              <span style={{ color: 'green' }}>无中奖</span>
            ) : (
              <span style={{ color: '#666' }}>等待开奖数据...</span>
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

      {/* 调试信息 */}
      {process.env.NODE_ENV === 'development' && (
        <details style={{ marginTop: '1rem', fontSize: '0.8rem' }}>
          <summary>调试信息</summary>
          <div style={{ background: '#f5f5f5', padding: '0.5rem', borderRadius: '4px' }}>
            <p><strong>批次数据:</strong> {JSON.stringify(data, null, 2)}</p>
            <p><strong>开奖结果:</strong> {JSON.stringify(lotteryResult, null, 2)}</p>
            <p><strong>中奖注数:</strong> {winningBets.length}</p>
          </div>
        </details>
      )}
    </div>
  );
};

export default SettlementCard;
