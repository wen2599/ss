import React, { useState, useMemo } from 'react';
import { apiService } from '../api';

/**
 * SettlementCard 组件
 * 负责展示单个下注批次的识别概要、结算结果，并提供编辑功能。
 * @param {object} batch - 从后端获取的单个下注批次数据。
 * @param {object} lotteryResult - 与此批次彩票类型匹配的最新开奖结果。
 * @param {function} onUpdate - 当用户保存修改时调用的回调函数。
 */
const SettlementCard = ({ batch, lotteryResult, onUpdate }) => {
  const { batch_id, data } = batch;

  // --- State for Edit Mode ---
  const [isEditing, setIsEditing] = useState(false);
  // 将 data.bets 格式化为可读的 JSON 字符串，用于 textarea
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));
  const [isSaving, setIsSaving] = useState(false);

  // --- Calculation Logic using useMemo for performance ---
  // 只有当 data.bets 或 lotteryResult 变化时，才会重新计算
  const { totalBetAmount, winningBets, summaryText } = useMemo(() => {
    let total = 0;
    const winners = [];
    const specialNumber = lotteryResult?.winning_numbers[6]; // 第7个号码是特码
    const betSummary = {}; // 用于生成概要文本, e.g., { '10': 2, '5': 6 }

    if (Array.isArray(data.bets)) {
      data.bets.forEach(bet => {
        const amount = Number(bet.amount) || 0;
        // 目前只计算 '号码' 或 '特码' 玩法
        if ((bet.bet_type === '号码' || bet.bet_type === '特码') && Array.isArray(bet.targets)) {
          bet.targets.forEach(targetNumber => {
            total += amount;

            // 累加金额概要
            betSummary[amount] = (betSummary[amount] || 0) + 1;

            // 检查是否中奖
            if (lotteryResult && specialNumber && String(targetNumber).trim() === String(specialNumber).trim()) {
              winners.push({ number: targetNumber, amount: amount });
            }
          });
        }
        // TODO: 在此可以添加对 '生肖' 等其他玩法的计算逻辑
      });
    }
    
    // 生成概要文本
    const summaryParts = Object.entries(betSummary).map(([amount, count]) => `${amount}元x${count}个`);
    const summary = `总下注 ${total} 元 (${summaryParts.join(', ')})`;

    return { totalBetAmount: total, winningBets: winners, summaryText: summary };
  }, [data.bets, lotteryResult]);

  // --- Event Handlers ---
  const handleSave = async () => {
    setIsSaving(true);
    try {
      const updatedBets = JSON.parse(editableData);
      // 检查解析出的数据是否是数组
      if (!Array.isArray(updatedBets)) {
          throw new Error("JSON 格式必须是一个数组 [...]");
      }

      const updatedBatchData = { ...data, bets: updatedBets };
      
      await apiService.updateBetBatch(batch_id, updatedBatchData);
      
      // 调用父组件的回调函数，将更新后的数据传回父组件
      onUpdate(batch_id, updatedBatchData); 
      
      setIsEditing(false);
      alert('修改已保存！');
    } catch (e) {
      alert("JSON 格式错误或保存失败: " + e.message);
    } finally {
      setIsSaving(false);
    }
  };

  // --- Rendering Functions ---
  const renderWinningDetails = (odds) => {
    if (!lotteryResult) {
      return <span>等待开奖号码...</span>;
    }
    if (winningBets.length === 0) {
      return <span style={{ fontWeight: 'bold', color: 'green' }}>未中奖 | 净亏 {totalBetAmount} 元</span>;
    }
    
    const totalWinAmount = winningBets.reduce((sum, bet) => sum + (bet.amount * odds), 0);
    const netProfit = totalWinAmount - totalBetAmount;

    return (
      <>
        <span style={{ color: 'blue', fontWeight: 'bold' }}>中 {winningBets.length} 注, 赢 {totalWinAmount}元</span> |{' '}
        <span style={{ fontWeight: 'bold', color: netProfit >= 0 ? 'red' : 'green' }}>
          净{netProfit >= 0 ? '赢' : '亏'} {Math.abs(netProfit)} 元
        </span>
      </>
    );
  };

  return (
    <div className="settlement-card">
      <p style={{ whiteSpace: 'pre-wrap', borderBottom: '1px solid #eee', paddingBottom: '1rem', margin: '0 0 1rem 0', fontFamily: 'monospace' }}>
        {data.raw_text}
      </p>
      
      <div className="settlement-details">
        {/* AI 识别概要 */}
        <p style={{ color: '#c51162', fontSize: '1.2rem', margin: '0.5rem 0', fontWeight: 'bold' }}>
          {summaryText}
        </p>
        
        {/* 结算结果 */}
        <div className="results-grid">
          <p style={{ color: 'blue', fontSize: '1.2rem', margin: '0.5rem 0' }}>
            <strong>中奖详情:</strong> {winningBets.map(b => `${b.number}(${b.amount}元)`).join(', ') || '无'}
          </p>
          <p style={{ color: '#c51162', fontSize: '1.2rem', margin: '0.5rem 0' }}>
            <strong>赔率 45:</strong> {renderWinningDetails(45)}
          </p>
          <p style={{ color: '#c51162', fontSize: '1.2rem', margin: '0.5rem 0' }}>
            <strong>赔率 46:</strong> {renderWinningDetails(46)}
          </p>
          <p style={{ color: '#c51162', fontSize: '1.2rem', margin: '0.5rem 0' }}>
            <strong>赔率 47:</strong> {renderWinningDetails(47)}
          </p>
        </div>

        {/* 修改按钮 */}
        <button onClick={() => setIsEditing(!isEditing)} className="link-button" style={{ marginTop: '0.5rem', fontSize: '1rem' }}>
          {isEditing ? '取消修改' : '修改AI识别结果'}
        </button>
      </div>
      
      {/* 编辑模式下的文本域 */}
      {isEditing && (
        <div className="edit-mode" style={{ marginTop: '1rem' }}>
          <p style={{fontSize: '0.9rem', color: '#666', margin: '0 0 0.5rem 0'}}>请直接编辑以下代表下注内容的 JSON 数据：</p>
          <textarea 
            value={editableData} 
            onChange={(e) => setEditableData(e.target.value)} 
            style={{ 
              width: '98%', 
              height: '150px', 
              fontFamily: 'monospace', 
              fontSize: '0.9rem',
              border: '1px solid #ccc',
              padding: '5px'
            }}
          />
          <button onClick={handleSave} disabled={isSaving} style={{ marginTop: '0.5rem' }}>
            {isSaving ? '保存中...' : '保存修改'}
          </button>
        </div>
      )}
    </div>
  );
};

export default SettlementCard;