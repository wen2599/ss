// File: frontend/pages/SettlementDetailPage.jsx
// 这就是我们之前设计的交互式结算工作台
// 我把之前 EmailDetailPage 的完整代码几乎原封不动地搬到这里

import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';

// --- SettlementCard 子组件 ---
const SettlementCard = ({ batch, lotteryResult, onUpdate }) => {
  const { batch_id, data } = batch;
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));
  const [isSaving, setIsSaving] = useState(false);

  // --- 计算逻辑 ---
  let totalBetAmount = 0;
  let winningBets = []; // 存储中奖的投注项

  data.bets.forEach(bet => {
    if (bet.bet_type === '号码' || bet.bet_type === '特码') {
      bet.targets.forEach(targetNumber => {
        totalBetAmount += bet.amount;
        // 检查是否中特码
        if (lotteryResult && targetNumber === lotteryResult.winning_numbers[6]) {
          winningBets.push({ number: targetNumber, amount: bet.amount });
        }
      });
    }
    // TODO: 实现生肖等其他玩法的计算逻辑
  });

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
  
  // 渲染中奖详情
  const renderWinningDetails = (odds) => {
    if (winningBets.length === 0) return <span>无中奖</span>;
    const totalWinAmount = winningBets.reduce((sum, bet) => sum + (bet.amount * odds), 0);
    const netProfit = totalWinAmount - totalBetAmount;
    return (
        <span>
            中 {winningBets.length} 注, 赢 {totalWinAmount} | 
            <span style={{color: netProfit >= 0 ? 'red' : 'green', fontWeight: 'bold'}}>
              净{netProfit >= 0 ? '赢' : '亏'} {Math.abs(netProfit)} 元
            </span>
        </span>
    );
  };

  return (
    <div className="settlement-card" style={{ border: '1px dashed #ccc', padding: '1rem', margin: '1rem 0' }}>
      <p style={{whiteSpace: 'pre-wrap'}}><strong>原始文本:</strong> {data.raw_text}</p>
      <div className="card-summary" style={{color: 'red', fontWeight: 'bold'}}>
        AI识别概要: 总下注 {totalBetAmount} 元
        <button onClick={() => setIsEditing(!isEditing)} style={{ marginLeft: '1rem' }}>
          {isEditing ? '取消' : '修改'}
        </button>
      </div>
      
      {isEditing ? (
        <div className="edit-mode" style={{ marginTop: '1rem' }}>
          <textarea value={editableData} onChange={(e) => setEditableData(e.target.value)} style={{ width: '95%', height: '150px' }} />
          <button onClick={handleSave} disabled={isSaving}>{isSaving ? '保存中...' : '保存修改'}</button>
        </div>
      ) : lotteryResult && (
        <div className="results-grid" style={{marginTop: '1rem'}}>
            <p style={{color: 'blue'}}><strong>中奖详情:</strong> {winningBets.map(b => `${b.number}(${b.amount}元)`).join(', ') || '无'}</p>
            <p><strong>赔率 45:</strong> {renderWinningDetails(45)}</p>
            <p><strong>赔率 46:</strong> {renderWinningDetails(46)}</p>
            <p><strong>赔率 47:</strong> {renderWinningDetails(47)}</p>
        </div>
      )}
    </div>
  );
};


// --- 页面主组件 ---
function SettlementDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({ email_content: '', bet_batches: [] });
  const [lotteryResult, setLotteryResult] = useState(null);
  const [issueInput, setIssueInput] = useState({ type: '香港六合彩', issue: '' });

  useEffect(() => {
    apiService.getEmailDetails(emailId)
      .then(res => setPageData(res.data))
      .catch(setError)
      .finally(() => setLoading(false));
  }, [emailId]);

  const handleBatchUpdate = (batchId, updatedData) => { /* ... (与之前版本相同) */ };
  const handleFetchLottery = () => { /* ... (与之前版本相同) */ };
  const renderEmailWithCards = () => { /* ... (与之前版本相同) */ };
  
  if (loading) return <p>Loading settlement details...</p>;
  if (error) return <p>Error: {error.message}</p>;
  
  return (
    <div className="card">
      <h2>结算工作台 (基于邮件 ID: {emailId})</h2>
      {/* 结算操作区 */}
      <div className="settlement-controls"> {/* ... (与之前版本相同) */}</div>
      <hr/>
      {/* 邮件和卡片展示区 */}
      {renderEmailWithCards()}
      {/* TODO: 添加全局汇总卡片 */}
    </div>
  );
}

// 把之前省略的代码补全
const originalRenderEmailWithCards = (pageData, handleBatchUpdate, lotteryResult) => {
    let content = pageData.email_content;
    let lastIndex = 0;
    const parts = [];

    // 尝试在原文中注入卡片
    pageData.bet_batches.forEach(batch => {
        const index = content.indexOf(batch.data.raw_text, lastIndex);
        if (index !== -1) {
            parts.push(content.substring(lastIndex, index));
            parts.push(<SettlementCard key={batch.batch_id} batch={batch} onUpdate={handleBatchUpdate} lotteryResult={lotteryResult} />);
            lastIndex = index + batch.data.raw_text.length;
        }
    });
    parts.push(content.substring(lastIndex));

    return <div>{parts}</div>;
}

// 再次定义主组件，这次把所有逻辑都放进去
function CompleteSettlementDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({ email_content: '', bet_batches: [] });
  const [lotteryResult, setLotteryResult] = useState(null);
  const [issueInput, setIssueInput] = useState({ type: '香港六合彩', issue: '' });

  useEffect(() => {
    setLoading(true);
    apiService.getEmailDetails(emailId)
      .then(res => {
        if (res.status === 'success') setPageData(res.data);
        else setError({ message: res.message });
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
  
  const handleFetchLottery = () => {
      if (!issueInput.issue) { alert('请输入期号'); return; }
      setLotteryResult(null); // 清空旧结果
      apiService.getLotteryResultByIssue(issueInput.type, issueInput.issue)
          .then(res => {
            if (res.status === 'success') setLotteryResult(res.data);
            else alert(res.message);
          })
          .catch(err => alert(err.message));
  };
  
  if (loading) return <p>正在加载结算详情...</p>;
  if (error) return <p style={{color: 'red'}}>错误: {error.message}</p>;

  return (
    <div className="card">
      <h2>结算工作台 (基于邮件 ID: {emailId})</h2>
      
      <div className="settlement-controls" style={{border: '1px solid #eee', padding: '1rem', borderRadius: '8px', marginBottom: '2rem'}}>
          <select value={issueInput.type} onChange={e => setIssueInput({...issueInput, type: e.target.value})}>
              <option>香港六合彩</option>
              <option>新澳门六合彩</option>
              <option>老澳门六合彩</option>
          </select>
          <input type="text" placeholder="输入期号, e.g., 2025123" value={issueInput.issue} onChange={e => setIssueInput({...issueInput, issue: e.target.value})} style={{marginLeft: '1rem'}} />
          <button onClick={handleFetchLottery} style={{marginLeft: '1rem'}}>加载开奖号码</button>
          {lotteryResult && <p style={{marginTop: '1rem', fontWeight: 'bold'}}>当前开奖期: {lotteryResult.issue_number} | 特码: <span style={{color: 'red', fontSize: '1.2rem'}}>{lotteryResult.winning_numbers[6]}</span></p>}
      </div>
      
      {originalRenderEmailWithCards(pageData, handleBatchUpdate, lotteryResult)}
    </div>
  );
}

export default CompleteSettlementDetailPage;