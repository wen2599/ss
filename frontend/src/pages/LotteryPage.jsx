import React, { useState, useEffect } from 'react';
import './LotteryPage.css';

const LotteryPage = () => {
  const [lotteryData, setLotteryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchData = () => {
    setLoading(true);
    setError(null);
    fetch('/api/get_lottery_results?limit=1') // Fetch only the latest result
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
        if (data && data.status === 'success' && data.lottery_results && data.lottery_results.length > 0) {
          setLotteryData(data.lottery_results[0]); // Get the latest result
        } else {
          setLotteryData(null); // No data found or empty results
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
    if (loading && !lotteryData) {
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

    if (!lotteryData) {
        return <div className="card no-data-card">暂无开奖数据</div>;
    }

    return (
      <div className="card lottery-display-card">
        <h2 className="lottery-type">【{lotteryData.lottery_type || '未知彩票'}】</h2>
        <p className="lottery-issue">期号: {lotteryData.issue_number || '--'}</p>
        <p className="lottery-drawing-date">开奖日期: {lotteryData.drawing_date || '--'}</p>
        <div className="lottery-detail-section">
            <h3>开奖号码</h3>
            <p className="lottery-winning-numbers">{(lotteryData.winning_numbers || []).join(' ')}</p>
        </div>
        <div className="lottery-detail-section">
            <h3>生肖</h3>
            <p className="lottery-zodiac-signs">{(lotteryData.zodiac_signs || []).join(' ')}</p>
        </div>
        <div className="lottery-detail-section">
            <h3>颜色</h3>
            <p className="lottery-colors">{(lotteryData.colors || []).join(' ')}</p>
        </div>
        <p className="lottery-timestamp">数据更新于: {lotteryData.created_at ? new Date(lotteryData.created_at).toLocaleString() : '--'}</p>
      </div>
    );
  };

  return (
    <div className="page-container">
      <h1 className="page-title">最新开奖号码</h1>
      {renderContent()}
       <footer className="page-footer">
        <p>请以官方开奖结果为准</p>
      </footer>
    </div>
  );
};

export default LotteryPage;