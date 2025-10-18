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

  const getShortName = (fullName) => {
    if (fullName.includes('新澳门')) return '新澳';
    if (fullName.includes('老澳门')) return '老澳';
    if (fullName.includes('香港')) return '香港';
    return '未知';
  };

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
        <div className="lottery-banner">
          <div className="lottery-short-name">{getShortName(result.lottery_type)}</div>
          <div className="lottery-details">
            <p className="lottery-issue">
              {result.lottery_type} 第: {result.issue_number || '--'}期
            </p>
            <div className="lottery-numbers">
              {(result.winning_numbers || []).join(' ')}
            </div>
            <div className="lottery-extra-info">
              <span>{(result.zodiac_signs || []).join(' ')}</span>
              <span>{(result.colors || []).join(' ')}</span>
            </div>
          </div>
        </div>
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