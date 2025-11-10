// File: frontend/pages/EmailDetailPage.jsx (Final Intelligent Workbench Version)

import React, { useState, useEffect, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';
import SettlementCard from '../components/SettlementCard';
// #region --- 子组件: SettlementCard ---
const SettlementCard = ({ batch, lotteryResult, onUpdate }) => {
  const { batch_id, data } = batch;
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));
  const [isSaving, setIsSaving] = useState(false);

  // 使用 useMemo 进行性能优化，只有在数据变化时才重新计算
  const { totalBetAmount, winningBets } = useMemo(() => {
    let total = 0;
    const winners = [];
    const specialNumber = lotteryResult?.winning_numbers[6];

    if (Array.isArray(data.bets)) {
      data.bets.forEach(bet => {
        const amount = Number(bet.amount) || 0;
        if ((bet.bet_type === '号码' || bet.bet_type === '特码') && Array.isArray(bet.targets)) {
          bet.targets.forEach(targetNumber => {
            total += amount;
            if (lotteryResult && specialNumber && String(targetNumber) === String(specialNumber)) {
              winners.push({ number: targetNumber, amount: amount });
            }
          });
        }
        // TODO: 在此添加对'生肖'等其他玩法的计算逻辑
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
      onUpdate(batch_id, updatedBatchData);
      setIsEditing(false);
      alert('修改已保存！');
    } catch (e) {
      alert("JSON 格式错误或保存失败: " + e.message);
    } finally {
      setIsSaving(false);
    }
  };

  const renderWinningDetails = (odds) => {
    if (!lotteryResult) return <span>等待开奖号码...</span>;
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
        <p><strong>AI识别概要:</strong> 总下注 {totalBetAmount} 元</p>
        
        <div className="results-grid">
          <p><strong>赔率 45:</strong> {renderWinningDetails(45)}</p>
          <p><strong>赔率 46:</strong> {renderWinningDetails(46)}</p>
          <p><strong>赔率 47:</strong> {renderWinningDetails(47)}</p>
        </div>

        <button onClick={() => setIsEditing(!isEditing)} className="link-button" style={{ marginTop: '0.5rem', fontSize: '0.9rem' }}>
          {isEditing ? '取消修改' : '修改AI识别结果'}
        </button>
      </div>
      
      {isEditing && (
        <div className="edit-mode" style={{ marginTop: '1rem' }}>
          <p style={{fontSize: '0.9rem', color: '#666'}}>请直接编辑以下代表下注内容的 JSON 数据：</p>
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
// #endregion --- 子组件: SettlementCard ---


// #region --- 页面主组件 ---
function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({
    email_content: '',
    bet_batches: [],
    latest_lottery_results: {}
  });

  useEffect(() => {
    setLoading(true);
    apiService.getEmailDetails(emailId)
      .then(res => {
        if (res.status === 'success') {
          setPageData(res.data);
        } else {
          setError({ message: res.message });
        }
      })
      .catch(setError)
      .finally(() => setLoading(false));
  }, [emailId]);

  const handleBatchUpdate = (batchId, updatedData) => {
    setPageData(prevData => ({
      ...prevData,
      bet_batches: prevData.bet_batches.map(b => 
        b.batch_id === batchId ? { ...b, data: updatedData } : b
      )
    }));
  };
  
  // --- 全局合计计算 ---
  const globalTotals = useMemo(() => {
    let totalBet = 0;
    let totalWin45 = 0, totalWin46 = 0, totalWin47 = 0;

    if (pageData && Array.isArray(pageData.bet_batches)) {
        pageData.bet_batches.forEach(batch => {
            if (batch.data && Array.isArray(batch.data.bets)) {
                const lotteryResult = pageData.latest_lottery_results[batch.data.lottery_type];
                const specialNumber = lotteryResult?.winning_numbers[6];

                batch.data.bets.forEach(bet => {
                    const amount = Number(bet.amount) || 0;
                    if ((bet.bet_type === '号码' || bet.bet_type === '特码') && Array.isArray(bet.targets)) {
                        bet.targets.forEach(targetNumber => {
                            totalBet += amount;
                            if (lotteryResult && specialNumber && String(targetNumber) === String(specialNumber)) {
                                totalWin45 += amount * 45;
                                totalWin46 += amount * 46;
                                totalWin47 += amount * 47;
                            }
                        });
                    }
                });
            }
        });
    }
    return {
        totalBet,
        netProfit45: totalWin45 - totalBet,
        netProfit46: totalWin46 - totalBet,
        netProfit47: totalWin47 - totalBet,
    };
  }, [pageData]);

  // --- 渲染逻辑 ---
  if (loading) return <p>正在加载智能核算面板...</p>;
  if (error) return <p style={{color: 'red'}}>错误: {error.message}</p>;

  // 将邮件原文与结算卡片交错渲染
  const renderContentWithCards = () => {
    let remainingContent = pageData.email_content;
    const elements = [];
    
    pageData.bet_batches.forEach((batch, index) => {
      // 查找原始文本在邮件内容中的位置
      const rawText = batch.data.raw_text;
      const position = remainingContent.indexOf(rawText);
      
      if (position !== -1) {
        // 添加原始文本之前的部分
        elements.push(<span key={`text-${index}`}>{remainingContent.substring(0, position)}</span>);
        // 添加结算卡片
        const lotteryResult = pageData.latest_lottery_results[batch.data.lottery_type];
        elements.push(<SettlementCard key={batch.batch_id} batch={batch} lotteryResult={lotteryResult} onUpdate={handleBatchUpdate} />);
        // 更新剩余内容
        remainingContent = remainingContent.substring(position + rawText.length);
      }
    });

    // 添加最后剩余的文本
    elements.push(<span key="text-last">{remainingContent}</span>);
    
    return <pre className="email-content-background">{elements}</pre>;
  };

  return (
    <div className="card">
      <h2>智能核算面板 (邮件ID: {emailId})</h2>
      <hr />
      
      {/* 渲染邮件原文和嵌入的卡片 */}
      {renderContentWithCards()}

      <hr style={{ border: 'none', borderTop: '2px solid #ccc', margin: '2rem 0' }} />

      <h3>全局合计</h3>
      <div className="global-totals-card">
        <p><strong>总下注金额: {globalTotals.totalBet} 元</strong></p>
        <hr />
        <p><strong>赔率 45:</strong> 总盈亏 <span style={{ fontWeight: 'bold', color: globalTotals.netProfit45 >= 0 ? 'red' : 'green' }}>{globalTotals.netProfit45 >= 0 ? '+' : ''}{globalTotals.netProfit45} 元</span></p>
        <p><strong>赔率 46:</strong> 总盈亏 <span style={{ fontWeight: 'bold', color: globalTotals.netProfit46 >= 0 ? 'red' : 'green' }}>{globalTotals.netProfit46 >= 0 ? '+' : ''}{globalTotals.netProfit46} 元</span></p>
        <p><strong>赔率 47:</strong> 总盈亏 <span style={{ fontWeight: 'bold', color: globalTotals.netProfit47 >= 0 ? 'red' : 'green' }}>{globalTotals.netProfit47 >= 0 ? '+' : ''}{globalTotals.netProfit47} 元</span></p>
      </div>
    </div>
  );
}
// #endregion --- 页面主组件 ---

export default EmailDetailPage;