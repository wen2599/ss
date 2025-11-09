// File: frontend/pages/EmailDetailPage.jsx (New Core Workbench)

import React, { useState, useEffect, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';

// --- SettlementCard Sub-component ---
const SettlementCard = ({ batch, lotteryResult, onUpdate }) => {
  const { batch_id, data } = batch;
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));
  const [isSaving, setIsSaving] = useState(false);

  const { totalBetAmount, winningBets } = useMemo(() => {
    let total = 0;
    const winners = [];
    const specialNumber = lotteryResult?.winning_numbers?.[6];

    if (data.bets) {
      data.bets.forEach(bet => {
        if ((bet.bet_type === '号码' || bet.bet_type === '特码') && bet.targets) {
          bet.targets.forEach(targetNumber => {
            total += bet.amount;
            if (specialNumber && targetNumber === specialNumber) {
              winners.push({ number: targetNumber, amount: bet.amount });
            }
          });
        }
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
    if (winningBets.length === 0) return <span style={{ color: 'green' }}>未中奖</span>;
    const totalWinAmount = winningBets.reduce((sum, bet) => sum + (bet.amount * odds), 0);
    const netProfit = totalWinAmount - totalBetAmount;
    return (
      <>
        中 {winningBets.length} 注, 赢 {totalWinAmount} |{' '}
        <span style={{ fontWeight: 'bold', color: netProfit >= 0 ? 'red' : 'green' }}>
          净{netProfit >= 0 ? '赢' : '亏'} {Math.abs(netProfit)} 元
        </span>
      </>
    );
  };

  return (
    <div className="settlement-card">
      <p style={{ whiteSpace: 'pre-wrap', borderBottom: '1px solid #eee', paddingBottom: '1rem' }}>
        <strong>原始文本:</strong> {data.raw_text}
      </p>
      
      <div className="settlement-details">
        <p><strong>AI识别概要:</strong> 总下注 {totalBetAmount} 元</p>
        
        {lotteryResult && (
          <div className="results-grid">
            <p><strong>中奖详情:</strong> {winningBets.map(b => `${b.number}(${b.amount}元)`).join(', ') || '无'}</p>
            <p><strong>赔率 45:</strong> {renderWinningDetails(45)}</p>
            <p><strong>赔率 46:</strong> {renderWinningDetails(46)}</p>
            <p><strong>赔率 47:</strong> {renderWinningDetails(47)}</p>
          </div>
        )}

        <button onClick={() => setIsEditing(!isEditing)} className="link-button">
          {isEditing ? '取消修改' : '修改AI识别结果'}
        </button>
      </div>
      
      {isEditing && (
        <div className="edit-mode" style={{ marginTop: '1rem' }}>
          <textarea value={editableData} onChange={(e) => setEditableData(e.target.value)} style={{ width: '95%', height: '150px' }} />
          <button onClick={handleSave} disabled={isSaving}>{isSaving ? '保存中...' : '保存修改'}</button>
        </div>
      )}
    </div>
  );
};


// --- Main Page Component ---
function EmailDetailPage() {
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
        if (res.status === 'success') {
            setPageData(res.data);
            if (res.data.bet_batches && res.data.bet_batches.length > 0) {
                setIssueInput(prev => ({...prev, issue: res.data.bet_batches[0].issue_number}));
            }
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
  
  const handleFetchLottery = () => {
      if (!issueInput.issue) { alert('请输入期号以进行结算'); return; }
      setLotteryResult(null); 
      apiService.getLotteryResultByIssue(issueInput.type, issueInput.issue)
          .then(res => {
            if (res.status === 'success') {
                setLotteryResult(res.data);
            } else {
                alert(res.message);
            }
          })
          .catch(err => alert('获取开奖结果时出错: ' + err.message));
  };
  
  if (loading) return <p>正在加载邮件与结算详情...</p>;
  if (error) return <p style={{ color: 'red' }}>错误: {error.message}</p>;

  return (
    <div className="card">
      <h2>邮件详情 & 结算工作台 (ID: {emailId})</h2>
      
      <div className="settlement-controls">
        <select value={issueInput.type} onChange={e => setIssueInput({ ...issueInput, type: e.target.value })}>
          <option>香港六合彩</option>
          <option>新澳门六合彩</option>
          <option>老澳门六合彩</option>
        </select>
        <input type="text" placeholder="输入期号以进行结算" value={issueInput.issue} onChange={e => setIssueInput({ ...issueInput, issue: e.target.value })} />
        <button onClick={handleFetchLottery}>加载开奖号码</button>
        {lotteryResult && <p><strong>当前特码:</strong> <span style={{ color: 'red', fontSize: '1.2rem' }}>{lotteryResult.winning_numbers[6]}</span></p>}
      </div>
      
      <hr />
      
      <pre className="email-content-background">{pageData.email_content}</pre>

      {pageData.bet_batches.map(batch => (
        <SettlementCard key={batch.batch_id} batch={batch} lotteryResult={lotteryResult} onUpdate={handleBatchUpdate} />
      ))}
    </div>
  );
}

export default EmailDetailPage;
