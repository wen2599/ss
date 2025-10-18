import React, { useState, useEffect } from 'react';
import './LotteryPage.css';

const LotteryPage = () => {
  const [lotteryData, setLotteryData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchData = () => {
    setLoading(true);
    setError(null);
    fetch('/api/get_lottery_results')
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => {
            throw new Error(err.message || `网络错误 (状态: ${response.status})`);
          }).catch(() => {
            throw new Error(`网络错误 (状态: ${response.status})`);
          });
        }
        return response.json();
      })
      .then(data => {
        if (data && data.status === 'success' && Array.isArray(data.lottery_results)) {
          setLotteryData(data.lottery_results);
        } else {
          setLotteryData([]); // No data found or empty results
        }
      })
      .catch(error => {
        console.error('获取开奖数据时出错:', error);
        setError(error.message || '获取数据失败');
      })
      .finally(() => {
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchData();
    const intervalId = setInterval(fetchData, 30000); // Fetch every 30 seconds
    return () => clearInterval(intervalId);
  }, []);

  const renderContent = () => {
    if (loading && lotteryData.length === 0) {
      return <div className="card loading-card">加载中...</div>;
    }

    if (error) {
      return (
        <div className="card error-message">
          <h2>加载失败</h2>
          <p>{error}</p>
          <button onClick={fetchData} className="retry-button">重试</button>
        </div>
      );
    }

    if (lotteryData.length === 0) {
      return <div className="card no-data-card">暂无开奖数据</div>;
    }

    return lotteryData.map((result) => (
      <div key={result.id} className="card lottery-display-card">
        <h2 className="lottery-type">【{result.lottery_type || '未知彩票'}】</h2>
        <p className="lottery-issue">期号: {result.issue_number || '--'}</p>
        <p className="lottery-drawing-date">开奖日期: {result.drawing_date || '--'}</p>
        <div className="lottery-detail-section">
          <h3>开奖号码</h3>
          <p className="lottery-winning-numbers">{(result.winning_numbers || []).join(' ')}</p>
        </div>
        <div className="lottery-detail-section">
          <h3>生肖</h3>
          <p className="lottery-zodiac-signs">{(result.zodiac_signs || []).join(' ')}</p>
        </div>
        <div className="lottery-detail-section">
          <h3>颜色</h3>
          <p className="lottery-colors">{(result.colors || []).join(' ')}</p>
        </div>
        <p className="lottery-timestamp">数据更新于: {result.created_at ? new Date(result.created_at).toLocaleString() : '--'}</p>
      </div>
    ));
  };

  return (
    <div className="page-container">
      <h1 className="page-title">最新开奖号码</h1>
      <div className="lottery-results-container">
        {renderContent()}
      </div>
       <footer className="page-footer">
        <p>请以官方开奖结果为准</p>
      </footer>
    </div>
  );
};

export default LotteryPage;