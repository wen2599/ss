// File: frontend/src/pages/EmailDetailPage.jsx (添加批量重新解析功能)
import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { apiService } from '../api';
import SingleBetCard from '../components/SingleBetCard';

function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [viewMode, setViewMode] = useState('split');
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({
    email_content: '',
    lines: []
  });
  const [hasOddsTemplate, setHasOddsTemplate] = useState(true);
  const [reparsing, setReparsing] = useState(false);
  const [showReparseModal, setShowReparseModal] = useState(false);

  useEffect(() => {
    fetchEmailLines();
    checkOddsTemplate();
  }, [emailId]);

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
      setHasOddsTemplate(false);
    }
  };

  const fetchEmailLines = () => {
    setLoading(true);
    setError(null);

    console.log('正在获取邮件ID:', emailId);

    apiService.splitEmailLines(emailId)
      .then(res => {
        console.log('拆分结果:', res);
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

  // 批量重新解析所有行
  const handleBatchReparse = async (selectedTypes) => {
    if (!selectedTypes || selectedTypes.length === 0) {
      alert('请选择至少一种彩票类型');
      return;
    }

    setReparsing(true);
    setShowReparseModal(false);

    const lotteryType = selectedTypes[0]; // 使用第一个选择的类型

    try {
      // 批量解析所有未解析的行
      const unparsedLines = pageData.lines.filter(line => !line.is_parsed);
      
      if (unparsedLines.length === 0) {
        alert('所有行都已解析完成！');
        setReparsing(false);
        return;
      }

      let successCount = 0;
      let errorCount = 0;

      // 顺序执行解析，避免并发问题
      for (const line of unparsedLines) {
        try {
          const numericEmailId = parseInt(emailId, 10);
          if (isNaN(numericEmailId)) {
            throw new Error('无效的邮件ID');
          }

          const result = await apiService.parseSingleBet(
            numericEmailId,
            line.text,
            line.line_number,
            lotteryType
          );

          if (result.status === 'success') {
            handleLineUpdate(line.line_number, result.data);
            successCount++;
          } else {
            console.error(`解析第${line.line_number}行失败:`, result.message);
            errorCount++;
          }
        } catch (error) {
          console.error(`解析第${line.line_number}行时发生错误:`, error);
          errorCount++;
        }

        // 添加小延迟，避免请求过于频繁
        await new Promise(resolve => setTimeout(resolve, 100));
      }

      alert(`批量解析完成！成功: ${successCount} 条，失败: ${errorCount} 条`);
      
    } catch (error) {
      console.error('批量解析失败:', error);
      alert('批量解析失败: ' + error.message);
    } finally {
      setReparsing(false);
    }
  };

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
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button
            onClick={() => setShowReparseModal(true)}
            disabled={reparsing}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: reparsing ? '#6c757d' : '#28a745',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: reparsing ? 'not-allowed' : 'pointer',
              fontSize: '0.9rem'
            }}
          >
            {reparsing ? '批量解析中...' : '批量重新解析'}
          </button>
          <button
            onClick={() => setViewMode('original')}
            style={{
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
            <strong>未解析:</strong> {pageData.lines.length - globalStats.parsedCount}
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
              💡 提示：系统已自动识别并拆分出 {pageData.lines.length} 条独立下注单，已解析 {globalStats.parsedCount} 条，未解析 {pageData.lines.length - globalStats.parsedCount} 条
            </small>
          </div>

          {pageData.lines.map(line => (
            <SingleBetCard
              key={line.line_number}
              lineData={line}
              emailId={emailId}
              onUpdate={handleLineUpdate}
              onDelete={handleLineDelete}
              showParseButton={false} // 隐藏单条解析按钮
            />
          ))}
        </div>
      )}

      {/* 批量重新解析模态框 */}
      {showReparseModal && (
        <BatchReparseModal
          isOpen={showReparseModal}
          onClose={() => setShowReparseModal(false)}
          onConfirm={handleBatchReparse}
          loading={reparsing}
          unparsedCount={pageData.lines.length - globalStats.parsedCount}
        />
      )}
    </div>
  );
}

// 批量重新解析模态框组件
function BatchReparseModal({ isOpen, onClose, onConfirm, loading, unparsedCount }) {
  const [selectedTypes, setSelectedTypes] = useState([]);

  const lotteryTypes = [
    { value: '香港六合彩', label: '香港六合彩 (周二、四、六开奖)' },
    { value: '新澳门六合彩', label: '新澳门六合彩 (每日开奖)' },
    { value: '老澳门六合彩', label: '老澳门六合彩 (每日开奖)' }
  ];

  const handleTypeToggle = (type) => {
    setSelectedTypes([type]); // 单选，只允许选择一个
  };

  const handleConfirm = () => {
    if (selectedTypes.length === 0) {
      alert('请选择一种彩票类型');
      return;
    }
    onConfirm(selectedTypes);
  };

  if (!isOpen) return null;

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      backgroundColor: 'rgba(0,0,0,0.5)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 1000
    }}>
      <div style={{
        backgroundColor: 'white',
        padding: '2rem',
        borderRadius: '8px',
        minWidth: '400px',
        maxWidth: '500px'
      }}>
        <h3 style={{ marginTop: 0, marginBottom: '1.5rem' }}>批量重新解析</h3>

        <div style={{ marginBottom: '1.5rem' }}>
          <p><strong>未解析行数:</strong> {unparsedCount}</p>
          <p style={{ fontSize: '0.9rem', color: '#666' }}>
            系统将自动解析所有未解析的下注单行
          </p>
        </div>

        <div style={{ marginBottom: '1.5rem' }}>
          <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: 'bold' }}>
            选择彩票类型:
          </label>
          {lotteryTypes.map(type => (
            <div key={type.value} style={{ marginBottom: '0.5rem' }}>
              <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer' }}>
                <input
                  type="radio"
                  name="batchLotteryType"
                  checked={selectedTypes.includes(type.value)}
                  onChange={() => handleTypeToggle(type.value)}
                  style={{ marginRight: '0.5rem' }}
                />
                {type.label}
              </label>
            </div>
          ))}
        </div>

        <div style={{
          backgroundColor: '#fff3cd',
          border: '1px solid #ffeaa7',
          borderRadius: '4px',
          padding: '1rem',
          marginBottom: '1.5rem'
        }}>
          <p style={{ margin: 0, color: '#856404', fontSize: '0.9rem' }}>
            💡 提示：批量解析将自动处理所有未解析的下注单，解析过程可能需要一些时间
          </p>
        </div>

        <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end' }}>
          <button
            onClick={onClose}
            disabled={loading}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#6c757d',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: loading ? 'not-allowed' : 'pointer'
            }}
          >
            取消
          </button>
          <button
            onClick={handleConfirm}
            disabled={loading || selectedTypes.length === 0}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: loading ? '#6c757d' : '#28a745',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: (loading || selectedTypes.length === 0) ? 'not-allowed' : 'pointer'
            }}
          >
            {loading ? '解析中...' : '开始批量解析'}
          </button>
        </div>
      </div>
    </div>
  );
}

export default EmailDetailPage;