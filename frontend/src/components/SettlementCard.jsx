// File: frontend/src/components/SettlementCard.jsx
import React, { useState, useMemo } from 'react';
import { apiService } from '../api';

const SettlementCard = ({ batch, lotteryResult, onUpdate }) => {
  const { batch_id, data } = batch;
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));
  const [isSaving, setIsSaving] = useState(false);

  // 使用 useMemo 进行性能优化，只有在数据变化时才重新计算
  const { totalBetAmount, winningBets } = useMemo(() => {
    let total = 0;
    const winners = [];
    const specialNumber = lotteryResult?.winning_numbers[6]; // 第7个是特码

    if (Array.isArray(data.bets)) {
        data.bets.forEach(bet => {
            if (bet.bet_type === '号码' || bet.bet_type === '特码') {
                if(Array.isArray(bet.targets)) {
                    bet.targets.forEach(targetNumber => {
                        total += (bet.amount || 0);
                        if (lotteryResult && specialNumber && targetNumber === specialNumber) {
                            winners.push({ number: targetNumber, amount: bet.amount });
                        }
                    });
                }
            }
            // TODO: 在这里可以添加对'生肖'等其他玩法的计算逻辑
        });
    }
    return { totalBetAmount: total, winningBets: winners };
  }, [data.bets, lotteryResult]);

  const handleSave = async () => {
    setIsSaving(true);
    try {
      const updatedBets = JSON.parse(editableData);
      const updatedBatchData = { ...data, bets: updatedBets };
      
      await apiService.updateBetBatch(batch_id, updatedBatchData);
      
      onUpdate(batch_id, updatedBatchData); // 通知父组件更新状态
      setIsEditing(false);
      alert('修改已保存！');
    } catch (e) {
      alert("JSON 格式错误或保存失败: " + e.message);
    } finally {
      setIsSaving(false);
    }
  };

  const renderWinningDetails = (odds) => {
    if (!lotteryResult) return <span>等待加载开奖号码...</span>;
    if (winningBets.length === 0) return <span style={{ color: 'green', fontWeight: 'bold' }}>未中奖 | 净亏 {totalBetAmount} 元</span>;
    
    const totalWinAmount = winningBets.reduce((sum, bet) => sum + (bet.amount * odds), 0);
    const netProfit = totalWinAmount - totalBetAmount;

    return (
      <>
        <span style={{ color: 'blue', fontWeight: 'bold' }}>中 {winningBets.length} 注, 赢 {totalWinAmount}元</span> | {' '}
        <span style={{ fontWeight: 'bold', color: netProfit >= 0 ? 'red' : 'green' }}>
          净{netProfit >= 0 ? '赢' : '亏'} {Math.abs(netProfit)} 元
        </span>
      </>
    );
  };

  return (
    <div className="settlement-card">
      <p style={{ whiteSpace: 'pre-wrap', borderBottom: '1px solid #eee', paddingBottom: '1rem' }}>
        {data.raw_text}
      </p>
      
      <div className="settlement-details">
        <p><strong>AI识别概要:</strong> 总下注 {totalBetAmount} 元</p>
        
        <div className="results-grid">
          <p><strong>赔率 45:</strong> {renderWinningDetails(45)}</p>
          <p><strong>赔率 46:</strong> {renderWinningDetails(46)}</p>
          <p><strong>赔率 47:</strong> {renderWinningDetails(47)}</p>
        </div>

        <button onClick={() => setIsEditing(!isEditing)} className="link-button" style={{ marginTop: '0.5rem' }}>
          {isEditing ? '取消修改' : '修改AI识别结果'}
        </button>
      </div>
      
      {isEditing && (
        <div className="edit-mode" style={{ marginTop: '1rem' }}>
          <p>请直接编辑以下代表下注内容的 JSON 数据：</p>
          <textarea 
            value={editableData} 
            onChange={(e) => setEditableData(e.target.value)} 
            style={{ width: '98%', height: '150px', fontFamily: 'monospace', fontSize: '0.9rem' }}
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