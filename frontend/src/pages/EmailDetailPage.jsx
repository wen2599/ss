// File: frontend/src/pages/EmailDetailPage.jsx (修改版)
import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom'; // 添加 Link
import { apiService } from '../api';
import SingleBetCard from '../components/SingleBetCard';

function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [viewMode, setViewMode] = useState('split'); // 'split' 或 'original'
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({
    email_content: '',
    lines: []
  });
  const [hasOddsTemplate, setHasOddsTemplate] = useState(true); // 新增 state

  useEffect(() => {
    fetchEmailLines();
    checkOddsTemplate(); // 新增调用
  }, [emailId]);

  // 新增函数：检查赔率模板
  const checkOddsTemplate = async () => {
    try {
      const response = await apiService.getOddsTemplate();
      if (response.status === 'success' && response.data) {
        const hasTemplate = Object.values(response.data).some(value => value !== null && value !== '');
        setHasOddsTemplate(hasTemplate);
      } else {
        setHasOddsTemplate(false);
      }
    } catch (error) {
      console.error('检查赔率模板失败:', error);
      setHasOddsTemplate(false); // 出错时也假设没有模板
    }
  };

  const fetchEmailLines = () => {
    setLoading(true);
    setError(null);

    apiService.splitEmailLines(emailId)
      .then(res => {
        if (res.status === 'success') {
          setPageData(res.data);
        } else {
          setError({ message: res.message || '获取数据失败' });
        }
      })
      .catch(err => {
        console.error('获取邮件行数据错误:', err);
        setError({ message: err.message || '网络请求失败' });
      })
      .finally(() => setLoading(false));
  };

  const handleLineUpdate = (lineNumber, updateData) => {
    setPageData(prev => ({
      ...prev,
      lines: prev.lines.map(line => 
        line.line_number === lineNumber 
          ? { 
              ...line, 
              is_parsed: true, 
              batch_data: {
                batch_id: updateData.batch_id,
                data: updateData.parse_result
              }
            } 
          : line
      )
    }));
  };

  const handleLineDelete = (lineNumber) => {
    setPageData(prev => ({
      ...prev,
      lines: prev.lines.map(line => 
        line.line_number === lineNumber 
          ? { ...line, is_parsed: false, batch_data: null } 
          : line
      )
    }));
  };

  // 计算全局统计
  const globalStats = pageData.lines.reduce((stats, line) => {
    if (line.is_parsed && line.batch_data?.data?.settlement) {
      const settlement = line.batch_data.data.settlement;
      stats.totalBet += settlement.total_bet_amount || 0;
      stats.totalWin += settlement.net_profits?.total_win || 0;
      stats.parsedCount++;
    }
    return stats;
  }, { totalBet: 0, totalWin: 0, parsedCount: 0 });

  if (loading) {
    return (
      <div className="card">
        <div style={{ textAlign: 'center', padding: '2rem' }}>
          <p>正在拆分邮件内容...</p>
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
          onClick={fetchEmailLines}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer'
          }}
        >
          重新加载
        </button>
      </div>
    );
  }

  return (
    <div className="card">
      {/* 新增赔率模板提示 */}
      {!hasOddsTemplate && (
        <div style={{
          backgroundColor: '#fff3cd',
          border: '1px solid #ffeaa7',
          borderRadius: '4px',
          padding: '1rem',
          marginBottom: '1rem'
        }}>
          <p style={{ margin: 0, color: '#856404' }}>
            ⚠️ 您还没有设置赔率模板，结算计算可能不准确。请先{' '}
            <Link to="/odds-template" style={{ color: '#007bff', fontWeight: 'bold' }}>
              设置赔率
            </Link>
          </p>
        </div>
      )}

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h2>智能解析面板 (邮件ID: {emailId})</h2>
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
            原始视图
          </button>
          <button
            onClick={() => setViewMode('split')}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: viewMode === 'split' ? '#007bff' : '#f8f9fa',
              color: viewMode === 'split' ? 'white' : '#333',
              border: '1px solid #ddd',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            分条解析
          </button>
        </div>
      </div>

      {/* 统计信息 */}
      <div style={{
        backgroundColor: '#e7f3ff',
        border: '1px solid #b3d9ff',
        borderRadius: '8px',
        padding: '1rem',
        marginBottom: '1.5rem'
      }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap' }}>
          <div>
            <strong>总条数:</strong> {pageData.lines.length}
          </div>
          <div>
            <strong>已解析:</strong> {globalStats.parsedCount}
          </div>
          <div>
            <strong>总下注:</strong> {globalStats.totalBet} 元
          </div>
          <div>
            <strong>总中奖:</strong> {globalStats.totalWin} 元
          </div>
          <div style={{
            color: (globalStats.totalWin - globalStats.totalBet) >= 0 ? 'red' : 'blue',
            fontWeight: 'bold'
          }}>
            <strong>净盈亏:</strong> {globalStats.totalWin - globalStats.totalBet >= 0 ? '+' : ''}
            {globalStats.totalWin - globalStats.totalBet} 元
          </div>
        </div>
      </div>

      {/* 视图内容 */}
      {viewMode === 'original' ? (
        <div>
          <h3>原始邮件内容</h3>
          <pre
            className="email-content-background"
            style={{
              whiteSpace: 'pre-wrap',
              wordBreak: 'break-word',
              lineHeight: '1.5',
              fontSize: '14px'
            }}
          >
            {pageData.email_content}
          </pre>
        </div>
      ) : (
        <div>
          <h3>分条解析 ({pageData.lines.length} 条)</h3>
          <div style={{
            padding: '0.5rem',
            backgroundColor: '#f8f9fa',
            borderRadius: '4px',
            marginBottom: '1rem'
          }}>
            <small>
              💡 提示：系统已自动识别并拆分出 {pageData.lines.length} 条独立下注单，请逐条解析查看结果
            </small>
          </div>

          {pageData.lines.map(line => (
            <SingleBetCard
              key={line.line_number}
              lineData={line}
              emailId={emailId}
              onUpdate={handleLineUpdate}
              onDelete={handleLineDelete}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export default EmailDetailPage;
