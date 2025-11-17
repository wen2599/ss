// File: frontend/src/pages/EmailDetailPage.jsx (修复 emailId 传递问题)
import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { apiService } from '../api';
import SingleBetCard from '../components/SingleBetCard';

// 批量重新解析模态框组件
function BatchReparseModal({ isOpen, onClose, onConfirm, loading, unparsedCount }) {
  const [selectedTypes, setSelectedTypes] = useState([]);
  const lotteryTypes = [
    { value: '香港六合彩', label: '香港六合彩 (周二、四、六开奖)' },
    { value: '新澳门六合彩', label: '新澳门六合彩 (每日开奖)' },
    { value: '老澳门六合彩', label: '老澳门六合彩 (每日开奖)' }
  ];
  const handleTypeToggle = (type) => {
    setSelectedTypes([type]);
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
    <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
      <div style={{ backgroundColor: 'white', padding: '2rem', borderRadius: '8px', minWidth: '400px', maxWidth: '500px' }}>
        <h3 style={{ marginTop: 0, marginBottom: '1.5rem' }}>批量重新解析</h3>
        <div style={{ marginBottom: '1.5rem' }}>
          <p><strong>未解析行数:</strong> {unparsedCount}</p>
        </div>
        <div style={{ marginBottom: '1.5rem' }}>
          <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: 'bold' }}>选择彩票类型:</label>
          {lotteryTypes.map(type => (
            <div key={type.value} style={{ marginBottom: '0.5rem' }}>
              <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer' }}>
                <input type="radio" name="batchLotteryType" checked={selectedTypes.includes(type.value)} onChange={() => handleTypeToggle(type.value)} style={{ marginRight: '0.5rem' }} />
                {type.label}
              </label>
            </div>
          ))}
        </div>
        <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end' }}>
          <button onClick={onClose} disabled={loading} style={{ padding: '0.5rem 1rem', backgroundColor: '#6c757d', color: 'white', border: 'none', borderRadius: '4px' }}>取消</button>
          <button onClick={handleConfirm} disabled={loading || selectedTypes.length === 0} style={{ padding: '0.5rem 1rem', backgroundColor: (loading || selectedTypes.length === 0) ? '#6c757d' : '#28a745', color: 'white', border: 'none', borderRadius: '4px' }}>{loading ? '解析中...' : '开始批量解析'}</button>
        </div>
      </div>
    </div>
  );
}

function EmailDetailPage() {
  const { emailId } = useParams();
  const [loading, setLoading] = useState(true);
  const [viewMode, setViewMode] = useState('split');
  const [error, setError] = useState(null);
  const [pageData, setPageData] = useState({ email_content: '', lines: [] });
  const [hasOddsTemplate, setHasOddsTemplate] = useState(true);
  const [reparsing, setReparsing] = useState(false);
  const [showReparseModal, setShowReparseModal] = useState(false);

  // 确保 emailId 是数字
  const numericEmailId = parseInt(emailId, 10);

  useEffect(() => {
    fetchEmailLines();
    checkOddsTemplate();
  }, [emailId]);

  const checkOddsTemplate = async () => {
    try {
      const response = await apiService.getOddsTemplate();
      setHasOddsTemplate(response.status === 'success' && Object.values(response.data).some(v => v));
    } catch (error) {
      setHasOddsTemplate(false);
    }
  };

  const fetchEmailLines = () => {
    setLoading(true);
    setError(null);
    apiService.splitEmailLines(emailId)
      .then(res => {
        if (res.status === 'success') setPageData(res.data);
        else setError({ message: res.message || '获取数据失败' });
      })
      .catch(err => setError({ message: err.message || '网络请求失败' }))
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

  const handleBatchReparse = async (selectedTypes) => {
    if (!selectedTypes || selectedTypes.length === 0) return alert('请选择至少一种彩票类型');
    setReparsing(true);
    setShowReparseModal(false);
    const lotteryType = selectedTypes[0];
    const unparsedLines = pageData.lines.filter(line => !line.is_parsed);
    if (unparsedLines.length === 0) {
      alert('所有行都已解析完成！');
      setReparsing(false);
      return;
    }
    let successCount = 0, errorCount = 0;
    for (const line of unparsedLines) {
      try {
        const result = await apiService.parseSingleBet(numericEmailId, line.text, line.line_number, lotteryType);
        if (result.status === 'success') {
          handleLineUpdate(line.line_number, result.data);
          successCount++;
        } else {
          errorCount++;
        }
      } catch (error) {
        errorCount++;
      }
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    alert(`批量解析完成！成功: ${successCount} 条，失败: ${errorCount} 条`);
    setReparsing(false);
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

  if (loading) return <div className="card"><p>正在拆分邮件内容...</p></div>;
  if (error) return <div className="card"><p style={{ color: 'red' }}>错误: {error.message}</p><button onClick={fetchEmailLines}>重新加载</button></div>;

  return (
    <div className="card">
      {!hasOddsTemplate && (
        <div style={{ backgroundColor: '#fff3cd', border: '1px solid #ffeaa7', padding: '1rem', marginBottom: '1rem' }}>
          <p style={{ margin: 0 }}>⚠️ 您还没有设置赔率模板，结算计算可能不准确。请先 <Link to="/odds-template">设置赔率</Link></p>
        </div>
      )}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h2>智能解析面板 (邮件ID: {emailId})</h2>
        <div>
          <button onClick={() => setShowReparseModal(true)} disabled={reparsing}>{reparsing ? '批量解析中...' : '批量重新解析'}</button>
          <button onClick={() => setViewMode('original')}>原始视图</button>
          <button onClick={() => setViewMode('split')}>分条解析</button>
        </div>
      </div>
      <div style={{ backgroundColor: '#e7f3ff', border: '1px solid #b3d9ff', padding: '1rem', marginBottom: '1.5rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap' }}>
          <div><strong>总条数:</strong> {pageData.lines.length}</div>
          <div><strong>已解析:</strong> {globalStats.parsedCount}</div>
          <div><strong>未解析:</strong> {pageData.lines.length - globalStats.parsedCount}</div>
          <div><strong>总下注:</strong> {globalStats.totalBet} 元</div>
          <div><strong>总中奖:</strong> {globalStats.totalWin} 元</div>
          <div style={{ color: (globalStats.totalWin - globalStats.totalBet) >= 0 ? 'red' : 'blue', fontWeight: 'bold' }}>
            <strong>净盈亏:</strong> {(globalStats.totalWin - globalStats.totalBet) >= 0 ? '+' : ''}{globalStats.totalWin - globalStats.totalBet} 元
          </div>
        </div>
      </div>
      {viewMode === 'original' ? (
        <pre className="email-content-background">{pageData.email_content}</pre>
      ) : (
        <div>
          {pageData.lines.map(line => (
            <SingleBetCard
              key={line.line_number}
              lineData={line}
              emailId={numericEmailId} // 确保传递数字类型的 emailId
              onUpdate={handleLineUpdate}
              onDelete={handleLineDelete}
            />
          ))}
        </div>
      )}
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

export default EmailDetailPage;