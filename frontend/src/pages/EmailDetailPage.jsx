import React, { useState, useEffect, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';
import SettlementCard from '../components/SettlementCard'; // <-- 【关键】在这里正确导入 SettlementCard 组件

/**
 * EmailDetailPage 组件
 * 作为“智能核算面板”，负责展示邮件原文、加载 AI 解析的下注批次，
 * 并自动匹配最新开奖结果，进行即时结算演算和汇总。
 */
function EmailDetailPage() {
  const { emailId } = useParams();

  // --- State Management ---
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({
    email_content: '',
    bet_batches: [],
    latest_lottery_results: {}
  });

  // --- Data Fetching ---
  useEffect(() => {
    setLoading(true);
    // 页面加载时，调用后端的“超级接口”一次性获取所有需要的数据
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
  }, [emailId]); // 当 emailId 变化时（例如从一个详情页跳转到另一个），重新获取数据

  // --- Event Handlers ---
  // 当子组件 SettlementCard 保存修改后，此函数被调用以更新整个页面的状态
  const handleBatchUpdate = (batchId, updatedData) => {
    setPageData(prevData => ({
      ...prevData,
      bet_batches: prevData.bet_batches.map(b => 
        b.batch_id === batchId ? { ...b, data: updatedData } : b
      )
    }));
  };
  
  // --- Global Totals Calculation ---
  const globalTotals = useMemo(() => {
    let totalBet = 0;
    let totalWin45 = 0, totalWin46 = 0, totalWin47 = 0;

    if (pageData && Array.isArray(pageData.bet_batches)) {
        pageData.bet_batches.forEach(batch => {
            // 确保 batch.data 和 batch.data.bets 存在且是数组
            if (batch.data && Array.isArray(batch.data.bets)) {
                // 为当前批次找到对应的开奖结果
                const lotteryResult = pageData.latest_lottery_results[batch.data.lottery_type];
                const specialNumber = lotteryResult?.winning_numbers[6];

                batch.data.bets.forEach(bet => {
                    const amount = Number(bet.amount) || 0;
                    if ((bet.bet_type === '号码' || bet.bet_type === '特码') && Array.isArray(bet.targets)) {
                        bet.targets.forEach(targetNumber => {
                            totalBet += amount;
                            // 只有在开奖结果存在时才计算中奖
                            if (lotteryResult && specialNumber && String(targetNumber).trim() === String(specialNumber).trim()) {
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
  }, [pageData]); // 只有当 pageData 变化时（加载完成或用户修改），才重新计算总计

  // --- Rendering Logic ---
  if (loading) {
    return <div className="card"><p>正在加载智能核算面板...</p></div>;
  }
  if (error) {
    return <div className="card" style={{color: 'red'}}><p>错误: {error.message}</p></div>;
  }

  // 将邮件原文与结算卡片交错渲染的函数
  const renderContentWithCards = () => {
    let remainingContent = pageData.email_content;
    const elements = [];
    
    pageData.bet_batches.forEach((batch, index) => {
      // AI 返回的原始文本可能包含特殊字符，需要一个稳健的查找方式
      const rawText = batch.data.raw_text;
      if (!rawText) return; // 如果没有原始文本，无法定位，跳过

      const position = remainingContent.indexOf(rawText);
      
      if (position !== -1) {
        // 添加原始文本之前的部分
        elements.push(<span key={`text-${index}`}>{remainingContent.substring(0, position)}</span>);
        
        // 找到与当前批次匹配的开奖结果
        const lotteryResult = pageData.latest_lottery_results[batch.data.lottery_type];
        
        // 添加结算卡片
        elements.push(<SettlementCard key={batch.batch_id} batch={batch} lotteryResult={lotteryResult} onUpdate={handleBatchUpdate} />);
        
        // 更新剩余内容
        remainingContent = remainingContent.substring(position + rawText.length);
      }
    });

    // 添加最后剩余的文本
    elements.push(<span key="text-last">{remainingContent}</span>);
    
    // 使用 pre 标签来保留原文的换行和空格
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

export default EmailDetailPage;