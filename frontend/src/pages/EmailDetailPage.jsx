// File: frontend/pages/EmailDetailPage.jsx (Final Version)
import React, { useState, useEffect, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';
import SettlementCard from '../components/SettlementCard'; // 引入新的子组件

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
    if (!issueInput.issue) { alert('请输入期号'); return; }
    setLotteryResult(null);
    apiService.getLotteryResultByIssue(issueInput.type, issueInput.issue)
      .then(res => {
        if (res.status === 'success') setLotteryResult(res.data);
        else alert(res.message);
      })
      .catch(err => alert(err.message));
  };

  // --- 全局合计计算 ---
  const globalTotals = useMemo(() => {
    let totalBet = 0;
    let totalWin45 = 0, totalWin46 = 0, totalWin47 = 0;

    if (lotteryResult && Array.isArray(pageData.bet_batches)) {
        const specialNumber = lotteryResult.winning_numbers[6];
        pageData.bet_batches.forEach(batch => {
            if(Array.isArray(batch.data.bets)) {
                batch.data.bets.forEach(bet => {
                    if (bet.bet_type === '号码' || bet.bet_type === '特码') {
                        if (Array.isArray(bet.targets)) {
                            bet.targets.forEach(targetNumber => {
                                totalBet += (bet.amount || 0);
                                if (targetNumber === specialNumber) {
                                    totalWin45 += (bet.amount || 0) * 45;
                                    totalWin46 += (bet.amount || 0) * 46;
                                    totalWin47 += (bet.amount || 0) * 47;
                                }
                            });
                        }
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
  }, [pageData.bet_batches, lotteryResult]);


  if (loading) return <p>正在加载结算详情...</p>;
  if (error) return <p style={{color: 'red'}}>错误: {error.message}</p>;

  return (
    <div className="card">
      <h2>结算工作台 (基于邮件 ID: {emailId})</h2>
      
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

export default EmailDetailPage;
