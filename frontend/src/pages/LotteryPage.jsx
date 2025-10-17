import React, { useState, useEffect } from 'react';
import './LotteryPage.css';

// Define the lottery types to be displayed
const LOTTERY_TYPES = [
  '新澳门六合彩',
  '香港六合彩',
  '老澳门六合彩'
];

// A single card component to display lottery results
const LotteryResultCard = ({ result, error }) => {
  if (error) {
    return (
      <div className="card error-message">
        <h2>{result.lottery_type}</h2>
        <p>未能加载最新开奖结果</p>
        <p className="error-detail">{error}</p>
      </div>
    );
  }

  if (!result || !result.issue_number) {
      return (
         <div className="card no-data-card">
            <h2>{result.lottery_type}</h2>
            <p>暂无开奖数据</p>
        </div>
      );
  }

  return (
    <div className="card lottery-display-card">
      <h2 className="lottery-type">【{result.lottery_type}】</h2>
      <p className="lottery-issue">期号: {result.issue_number}</p>
      <p className="lottery-drawing-date">开奖日期: {result.drawing_date}</p>
      <div className="lottery-detail-section">
        <h3>开奖号码</h3>
        <p className="lottery-winning-numbers">{result.winning_numbers}</p>
      </div>
      <div className="lottery-detail-section">
        <h3>生肖</h3>
        <p className="lottery-zodiac-signs">{result.zodiac_signs}</p>
      </div>
      <div className="lottery-detail-section">
        <h3>颜色</h3>
        <p className="lottery-colors">{result.colors}</p>
      </div>
      <p className="lottery-timestamp">数据更新于: {new Date(result.created_at).toLocaleString()}</p>
    </div>
  );
};


const LotteryPage = () => {
  const [results, setResults] = useState({});
  const [loading, setLoading] = useState(true);

  const fetchAllLotteryData = async () => {
    setLoading(true);

    // Use Promise.allSettled to ensure all requests complete, even if some fail
    const promises = LOTTERY_TYPES.map(type =>
      fetch(`/get_lottery_results.php?lottery_type=${encodeURIComponent(type)}&limit=1`)
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
            if (data && data.status === 'success' && data.lottery_results.length > 0) {
                return { type, data: data.lottery_results[0], error: null };
            }
            // Handle case where API returns success but no results
            return { type, data: null, error: '未找到结果' };
        })
        .catch(error => {
            console.error(`获取 ${type} 数据时出错:`, error);
            return { type, data: null, error: error.message };
        })
    );

    const outcomes = await Promise.all(promises);

    const newResults = {};
    outcomes.forEach(outcome => {
        newResults[outcome.type] = { data: outcome.data, error: outcome.error };
    });

    setResults(newResults);
    setLoading(false);
  };

  useEffect(() => {
    fetchAllLotteryData();
    const intervalId = setInterval(fetchAllLotteryData, 30000); // Refresh every 30 seconds
    return () => clearInterval(intervalId);
  }, []);

  const renderContent = () => {
    if (loading && Object.keys(results).length === 0) {
      return <div className="card loading-card">加载中...</div>;
    }

    return LOTTERY_TYPES.map(type => {
      const resultItem = results[type];
      return <LotteryResultCard key={type} result={{ lottery_type: type, ...resultItem?.data }} error={resultItem?.error} />;
    });
  };

  return (
    <div className="page-container">
      <h1 className="page-title">最新开奖号码</h1>
      <div className="lottery-results-grid">
        {renderContent()}
      </div>
       <footer className="page-footer">
        <p>请以官方开奖结果为准</p>
      </footer>
    </div>
  );
};

export default LotteryPage;