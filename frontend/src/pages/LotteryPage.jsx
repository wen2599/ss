import React, { useState, useEffect } from 'react';
import './LotteryPage.css';

const LotteryPage = () => {
  const [lotteryData, setLotteryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchData = () => {
    setLoading(true);
    setError(null);
    fetch('/api/getLotteryNumber')
      .then(response => {
        if (!response.ok) {
          // Try to get a more specific error message from the backend if possible
          return response.json().then(err => {
            throw new Error(err.message || `网络错误 (状态: ${response.status})`);
          }).catch(() => {
            throw new Error(`网络错误 (状态: ${response.status})`);
          });
        }
        return response.json();
      })
      .then(data => {
        if (data && data.winning_numbers) {
          setLotteryData(data);
        } else {
           // This case handles a successful API call but empty/invalid data, like the initial state
          setLotteryData({ winning_numbers: '等待开奖', issue_number: '--', created_at: '--' });
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
    const intervalId = setInterval(fetchData, 15000);
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

    return (
      <div className="card lottery-display-card">
        <h2 className="lottery-issue">期号: {lotteryData?.issue_number || '--'}</h2>
        <p className="lottery-number-display">{lotteryData?.winning_numbers || 'N/A'}</p>
        <p className="lottery-timestamp">最后更新: {lotteryData?.created_at ? new Date(lotteryData.created_at).toLocaleString() : '--'}</p>
      </div>
    );
  };

  return (
    <div className="page-container">
      <h1 className="page-title">最新开奖</h1>
      {renderContent()}
       <footer className="page-footer">
        <p>请以官方开奖结果为准</p>
      </footer>
    </div>
  );
};

export default LotteryPage;