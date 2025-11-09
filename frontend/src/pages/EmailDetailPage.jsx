// File: frontend/pages/EmailDetailPage.jsx
import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';

// SettlementCard 组件 (内联定义，也可以拆分出去)
const SettlementCard = ({ batch, lotteryResult }) => {
  // 这里的 batch 是 { batch_id: 1, data: { timestamp: "22:28", bets: [...] } }
  const { data } = batch;
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState(JSON.stringify(data.bets, null, 2));

  // --- 计算逻辑 ---
  let totalBetAmount = 0;
  let winningAmount = 0;
  const specialNumber = lotteryResult?.winning_numbers[6]; // 第7个是特码

  data.bets.forEach(bet => {
    // 假设 "号码" 玩法默认是特码
    if (bet.bet_type === '号码') {
        bet.targets.forEach(targetNumber => {
            totalBetAmount += bet.amount;
            if (specialNumber && targetNumber === specialNumber) {
                winningAmount += bet.amount; // 记录中的是哪个金额
            }
        });
    }
    // TODO: 添加生肖等其他玩法的计算
  });
  
  const handleSave = () => {
      try {
          const updatedBets = JSON.parse(editableData);
          // TODO: 调用 apiService.updateBetBatch(...)
          // 成功后更新父组件状态并退出编辑模式
          setIsEditing(false);
      } catch (e) {
          alert("JSON 格式错误！");
      }
  }

  return (
    <div className="settlement-card">
      <div className="card-summary">
        总下注: {totalBetAmount} 元
        <button onClick={() => setIsEditing(!isEditing)}>{isEditing ? '取消' : '修改'}</button>
      </div>
      
      {isEditing ? (
        <div className="edit-mode">
            <textarea value={editableData} onChange={(e) => setEditableData(e.target.value)} />
            <button onClick={handleSave}>保存修改</button>
        </div>
      ) : (
        <div className="results-grid">
            {winningAmount > 0 && <p style={{color: 'blue'}}>中奖金额: {winningAmount}</p>}
            <p>赔率 45: <span style={{color: winningAmount * 45 - totalBetAmount > 0 ? 'red' : 'green'}}>
                {winningAmount * 45 - totalBetAmount} 元
            </span></p>
            <p>赔率 46: <span style={{color: winningAmount * 46 - totalBetAmount > 0 ? 'red' : 'green'}}>
                {winningAmount * 46 - totalBetAmount} 元
            </span></p>
            <p>赔率 47: <span style={{color: winningAmount * 47 - totalBetAmount > 0 ? 'red' : 'green'}}>
                {winningAmount * 47 - totalBetAmount} 元
            </span></p>
        </div>
      )}
    </div>
  );
};


// 页面主组件
function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [emailData, setEmailData] = useState({ email_content: '', bet_batches: [] });
  const [lotteryResult, setLotteryResult] = useState(null);
  const [issueInput, setIssueInput] = useState({ type: '香港六合彩', issue: '' });

  useEffect(() => {
    apiService.getEmailDetails(emailId)
      .then(res => setEmailData(res.data))
      .catch(setError)
      .finally(() => setLoading(false));
  }, [emailId]);

  const handleFetchLottery = () => {
      if (!issueInput.issue) return;
      apiService.getLotteryResultByIssue(issueInput.type, issueInput.issue)
          .then(res => setLotteryResult(res.data))
          .catch(err => {
              alert(err.message);
              setLotteryResult(null);
          });
  };

  // 渲染邮件原文和结算卡片
  const renderEmailWithCards = () => {
      let content = emailData.email_content;
      // 注意：这是一个简化的实现，实际需要更复杂的 DOM 解析或标记替换
      // 这里我们简单地在邮件末尾附加所有卡片
      return (
          <div>
              <pre>{content}</pre>
              <hr/>
              <h3>结算卡片</h3>
              {emailData.bet_batches.map(batch => (
                  <div key={batch.batch_id} style={{border: '1px dashed #ccc', padding: '1rem', margin: '1rem 0'}}>
                      <p><strong>原始文本:</strong> {batch.data.raw_text}</p>
                      <SettlementCard batch={batch} lotteryResult={lotteryResult} />
                  </div>
              ))}
          </div>
      );
  }

  if (loading) return <p>Loading email details...</p>;
  if (error) return <p>Error: {error.message}</p>;

  return (
    <div className="card">
      <h2>邮件详情 & 结算 (ID: {emailId})</h2>
      
      {/* 结算操作区 */}
      <div className="settlement-controls">
          <select value={issueInput.type} onChange={e => setIssueInput({...issueInput, type: e.target.value})}>
              <option>香港六合彩</option>
              <option>新澳门六合彩</option>
              <option>老澳门六合彩</option>
          </select>
          <input type="text" placeholder="输入期号, e.g., 2025123" value={issueInput.issue} onChange={e => setIssueInput({...issueInput, issue: e.target.value})} />
          <button onClick={handleFetchLottery}>加载开奖号码</button>
          {lotteryResult && <p>当前特码: <strong>{lotteryResult.winning_numbers[6]}</strong></p>}
      </div>
      <hr/>

      {/* 邮件和卡片展示区 */}
      {renderEmailWithCards()}
    </div>
  );
}

export default EmailDetailPage;
