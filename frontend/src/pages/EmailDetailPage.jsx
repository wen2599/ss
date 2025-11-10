import React, { useState, useEffect, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';
import SettlementCard from '../components/SettlementCard';

/**
 * EmailDetailPage 组件 - 增强版
 * 显示嵌入结算结果的邮件内容
 */
function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({
    email_content: '',
    enhanced_content: '',
    bet_batches: [],
    latest_lottery_results: {}
  });
  const [viewMode, setViewMode] = useState('enhanced'); // 'original' 或 'enhanced'

  // 数据获取
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

  // 处理批次更新
  const handleBatchUpdate = (batchId, updatedData) => {
    setPageData(prevData => ({
      ...prevData,
      bet_batches: prevData.bet_batches.map(b =>
        b.batch_id === batchId ? { ...b, data: updatedData } : b
      )
    }));
  };

  // 全局总计计算
  const globalTotals = useMemo(() => {
    let totalBet = 0;
    let totalWin45 = 0, totalWin46 = 0, totalWin47 = 0;

    if (pageData && Array.isArray(pageData.bet_batches)) {
      pageData.bet_batches.forEach(batch => {
        if (batch.settlement) {
          totalBet += batch.settlement.total_bet_amount || 0;
          totalWin45 += batch.settlement.net_profits?.[45]?.total_win || 0;
          totalWin46 += batch.settlement.net_profits?.[46]?.total_win || 0;
          totalWin47 += batch.settlement.net_profits?.[47]?.total_win || 0;
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

  // 渲染内容
  const renderContent = () => {
    const content = viewMode === 'enhanced' ? 
      pageData.enhanced_content : 
      pageData.email_content;

    return (
      <pre 
        className="email-content-background"
        style={{ 
          whiteSpace: 'pre-wrap',
          wordBreak: 'break-word',
          lineHeight: '1.5',
          fontSize: '14px'
        }}
      >
        {content}
      </pre>
    );
  };

  // 渲染结算卡片
  const renderSettlementCards = () => {
    if (!Array.isArray(pageData.bet_batches)) return null;

    return pageData.bet_batches.map(batch => {
      if (!batch.settlement) return null;

      const lotteryResult = pageData.latest_lottery_results[batch.data.lottery_type];
      
      return (
        <SettlementCard 
          key={batch.batch_id} 
          batch={batch} 
          lotteryResult={lotteryResult} 
          onUpdate={handleBatchUpdate} 
        />
      );
    });
  };

  if (loading) {
    return <div className="card"><p>正在加载智能核算面板...</p></div>;
  }
  if (error) {
    return <div className="card" style={{color: 'red'}}><p>错误: {error.message}</p></div>;
  }

  return (
    <div className="card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h2>智能核算面板 (邮件ID: {emailId})</h2>
        <div>
          <button 
            onClick={() => setViewMode('original')}
            className={viewMode === 'original' ? 'active' : ''}
            style={{ 
              marginRight: '0.5rem',
              padding: '0.5rem 1rem',
              backgroundColor: viewMode === 'original' ? '#007bff' : '#f8f9fa',
              color: viewMode === 'original' ? 'white' : '#333',
              border: '1px solid #ddd',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            原始内容
          </button>
          <button 
            onClick={() => setViewMode('enhanced')}
            className={viewMode === 'enhanced' ? 'active' : ''}
            style={{ 
              padding: '0.5rem 1rem',
              backgroundColor: viewMode === 'enhanced' ? '#007bff' : '#f8f9fa',
              color: viewMode === 'enhanced' ? 'white' : '#333',
              border: '1px solid #ddd',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            结算视图
          </button>
        </div>
      </div>

      <hr />

      {/* 内容显示区域 */}
      <div style={{ 
        border: '1px solid #e0e0e0', 
        borderRadius: '8px', 
        padding: '1rem',
        backgroundColor: '#fafafa',
        marginBottom: '1rem'
      }}>
        {renderContent()}
      </div>

      {/* 结算卡片区域 */}
      {viewMode === 'original' && renderSettlementCards()}

      <hr style={{ border: 'none', borderTop: '2px solid #ccc', margin: '2rem 0' }} />

      {/* 全局合计 */}
      <h3>全局结算汇总</h3>
      <div className="global-totals-card">
        <p><strong>总下注金额: {globalTotals.totalBet} 元</strong></p>
        <hr />
        <p>
          <strong>赔率 45:</strong> 总盈亏{' '}
          <span style={{ 
            fontWeight: 'bold', 
            color: globalTotals.netProfit45 >= 0 ? 'red' : 'green' 
          }}>
            {globalTotals.netProfit45 >= 0 ? '+' : ''}{globalTotals.netProfit45} 元
          </span>
        </p>
        <p>
          <strong>赔率 46:</strong> 总盈亏{' '}
          <span style={{ 
            fontWeight: 'bold', 
            color: globalTotals.netProfit46 >= 0 ? 'red' : 'green' 
          }}>
            {globalTotals.netProfit46 >= 0 ? '+' : ''}{globalTotals.netProfit46} 元
          </span>
        </p>
        <p>
          <strong>赔率 47:</strong> 总盈亏{' '}
          <span style={{ 
            fontWeight: 'bold', 
            color: globalTotals.netProfit47 >= 0 ? 'red' : 'green' 
          }}>
            {globalTotals.netProfit47 >= 0 ? '+' : ''}{globalTotals.netProfit47} 元
          </span>
        </p>
      </div>

      {/* 批次信息 */}
      <div style={{ marginTop: '2rem' }}>
        <h4>AI识别批次信息</h4>
        {pageData.bet_batches.map(batch => (
          <div key={batch.batch_id} style={{ 
            border: '1px solid #e0e0e0', 
            padding: '0.5rem', 
            marginBottom: '0.5rem',
            borderRadius: '4px',
            backgroundColor: '#f8f9fa'
          }}>
            <small>
              批次 {batch.batch_id} | 模型: {batch.ai_model} | 
              彩票类型: {batch.data.lottery_type || '未知'} |
              期号: {batch.data.issue_number || '未知'}
            </small>
          </div>
        ))}
      </div>
    </div>
  );
}

export default EmailDetailPage;