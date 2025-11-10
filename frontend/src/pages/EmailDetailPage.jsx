import React, { useState, useEffect, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { apiService } from '../api';
import SettlementCard from '../components/SettlementCard';

function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [reanalyzing, setReanalyzing] = useState(false);
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({
    email_content: '',
    enhanced_content: '',
    bet_batches: [],
    latest_lottery_results: {}
  });
  const [viewMode, setViewMode] = useState('enhanced');

  // 数据获取
  useEffect(() => {
    fetchEmailDetails();
  }, [emailId]);

  const fetchEmailDetails = () => {
    setLoading(true);
    setError(null);

    apiService.getEmailDetails(emailId)
      .then(res => {
        if (res.status === 'success') {
          console.log('获取到的数据:', res.data);
          setPageData(res.data);
        } else {
          setError({ message: res.message || '获取数据失败' });
        }
      })
      .catch(err => {
        console.error('获取邮件详情错误:', err);
        setError({ message: err.message || '网络请求失败' });
      })
      .finally(() => setLoading(false));
  };

  // 重新解析邮件
  const handleReanalyze = async () => {
    setReanalyzing(true);
    try {
      const result = await apiService.reanalyzeEmail(parseInt(emailId));
      
      if (result.status === 'success') {
        alert('重新解析成功！');
        // 重新加载数据
        fetchEmailDetails();
      } else {
        alert('重新解析失败: ' + result.message);
      }
    } catch (error) {
      console.error('重新解析错误:', error);
      alert('重新解析请求失败: ' + error.message);
    } finally {
      setReanalyzing(false);
    }
  };

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

  // 渲染内容 - 修复版：正确处理HTML内容
  const renderContent = () => {
    const content = viewMode === 'enhanced' && pageData.enhanced_content 
      ? pageData.enhanced_content 
      : pageData.email_content;

    return (
      <pre
        className="email-content-background"
        style={{
          whiteSpace: 'pre-wrap',
          wordBreak: 'break-word',
          lineHeight: '1.5',
          fontSize: '14px',
          fontFamily: 'inherit',
          backgroundColor: '#f9f9f9',
          padding: '1rem',
          borderRadius: '8px',
          border: '1px solid #e0e0e0'
        }}
        dangerouslySetInnerHTML={{ __html: formatContentForDisplay(content) }}
      />
    );
  };

  // 格式化内容显示 - 处理HTML实体和换行
  const formatContentForDisplay = (content) => {
    if (!content) return '';
    
    // 替换HTML实体
    let formatted = content
      .replace(/&amp;/g, '&')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"')
      .replace(/&#039;/g, "'");
    
    // 确保换行正确显示
    formatted = formatted.replace(/\n/g, '<br/>');
    
    return formatted;
  };

  // 渲染结算卡片
  const renderSettlementCards = () => {
    if (!Array.isArray(pageData.bet_batches) || pageData.bet_batches.length === 0) {
      return (
        <div className="settlement-card" style={{
          border: '2px solid #ffc107',
          borderRadius: '8px',
          margin: '1rem 0',
          padding: '1rem',
          backgroundColor: '#fff3cd',
          textAlign: 'center'
        }}>
          <p style={{ color: '#856404', margin: '0 0 1rem 0' }}>
            📝 未检测到AI解析的下注信息
          </p>
          <button
            onClick={handleReanalyze}
            disabled={reanalyzing}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: reanalyzing ? '#6c757d' : '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: reanalyzing ? 'not-allowed' : 'pointer'
            }}
          >
            {reanalyzing ? '解析中...' : '重新解析邮件'}
          </button>
        </div>
      );
    }

    return pageData.bet_batches.map(batch => {
      const lotteryResult = pageData.latest_lottery_results[batch.data?.lottery_type];

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
    return (
      <div className="card">
        <div style={{ textAlign: 'center', padding: '2rem' }}>
          <p>正在加载智能核算面板...</p>
          <div style={{
            width: '40px',
            height: '40px',
            border: '4px solid #f3f3f3',
            borderTop: '4px solid #007bff',
            borderRadius: '50%',
            animation: 'spin 1s linear infinite',
            margin: '0 auto'
          }}></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="card" style={{ color: 'red', textAlign: 'center' }}>
        <h3>加载失败</h3>
        <p>错误: {error.message}</p>
        <button
          onClick={fetchEmailDetails}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer',
            marginRight: '0.5rem'
          }}
        >
          重新加载
        </button>
        <button
          onClick={handleReanalyze}
          disabled={reanalyzing}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: reanalyzing ? '#6c757d' : '#28a745',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: reanalyzing ? 'not-allowed' : 'pointer'
          }}
        >
          {reanalyzing ? '解析中...' : '强制重新解析'}
        </button>
      </div>
    );
  }

  return (
    <div className="card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h2>智能核算面板 (邮件ID: {emailId})</h2>
        <div>
          <button
            onClick={() => setViewMode('original')}
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

      {/* 操作按钮 */}
      <div style={{
        display: 'flex',
        gap: '0.5rem',
        marginBottom: '1rem',
        padding: '0.5rem',
        backgroundColor: '#f8f9fa',
        borderRadius: '4px'
      }}>
        <button
          onClick={handleReanalyze}
          disabled={reanalyzing}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: reanalyzing ? '#6c757d' : '#28a745',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: reanalyzing ? 'not-allowed' : 'pointer',
            fontSize: '0.9rem'
          }}
        >
          {reanalyzing ? '🔄 解析中...' : '🔄 重新解析邮件'}
        </button>
        <button
          onClick={fetchEmailDetails}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#17a2b8',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer',
            fontSize: '0.9rem'
          }}
        >
          🔄 刷新数据
        </button>
      </div>

      {/* 视图模式提示 */}
      <div style={{
        padding: '0.5rem',
        backgroundColor: viewMode === 'enhanced' ? '#e7f3ff' : '#f8f9fa',
        borderLeft: `4px solid ${viewMode === 'enhanced' ? '#007bff' : '#6c757d'}`,
        marginBottom: '1rem',
        borderRadius: '4px'
      }}>
        <small>
          当前模式: <strong>{viewMode === 'enhanced' ? '结算视图' : '原始内容'}</strong>
          {viewMode === 'enhanced' && pageData.enhanced_content === pageData.email_content &&
            ' - 未检测到结算信息，显示原始内容'}
        </small>
      </div>

      {/* 调试信息 */}
      {process.env.NODE_ENV === 'development' && (
        <details style={{ marginBottom: '1rem', border: '1px solid #ccc', padding: '0.5rem', borderRadius: '4px' }}>
          <summary>调试信息</summary>
          <div style={{ fontSize: '0.8rem', background: '#f5f5f5', padding: '0.5rem' }}>
            <p><strong>批次数量:</strong> {pageData.bet_batches?.length || 0}</p>
            <p><strong>增强内容长度:</strong> {pageData.enhanced_content?.length || 0}</p>
            <p><strong>原始内容长度:</strong> {pageData.email_content?.length || 0}</p>
            <p><strong>彩票结果:</strong> {Object.keys(pageData.latest_lottery_results || {}).length} 种</p>
            <p><strong>增强内容预览:</strong> {pageData.enhanced_content?.substring(0, 100)}...</p>
          </div>
        </details>
      )}

      <hr />

      {/* 内容显示区域 */}
      <div style={{
        border: '1px solid #e0e0e0',
        borderRadius: '8px',
        padding: '0',
        backgroundColor: '#fafafa',
        marginBottom: '1rem',
        minHeight: '200px',
        overflow: 'auto'
      }}>
        {renderContent()}
      </div>

      {/* 在原始视图下显示结算卡片 */}
      {viewMode === 'original' && (
        <>
          <h3>AI解析结果</h3>
          {renderSettlementCards()}
        </>
      )}

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
            color: globalTotals.netProfit45 >= 0 ? 'red' : 'blue'
          }}>
            {globalTotals.netProfit45 >= 0 ? '+' : ''}{globalTotals.netProfit45} 元
          </span>
        </p>
        <p>
          <strong>赔率 46:</strong> 总盈亏{' '}
          <span style={{
            fontWeight: 'bold',
            color: globalTotals.netProfit46 >= 0 ? 'red' : 'blue'
          }}>
            {globalTotals.netProfit46 >= 0 ? '+' : ''}{globalTotals.netProfit46} 元
          </span>
        </p>
        <p>
          <strong>赔率 47:</strong> 总盈亏{' '}
          <span style={{
            fontWeight: 'bold',
            color: globalTotals.netProfit47 >= 0 ? 'red' : 'blue'
          }}>
            {globalTotals.netProfit47 >= 0 ? '+' : ''}{globalTotals.netProfit47} 元
          </span>
        </p>
      </div>
    </div>
  );
}

// 添加旋转动画
const styles = `
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
`;

// 注入样式
const styleSheet = document.createElement('style');
styleSheet.innerText = styles;
document.head.appendChild(styleSheet);

export default EmailDetailPage;
