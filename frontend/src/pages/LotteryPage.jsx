import React, { useState, useEffect } from 'react';
import './LotteryPage.css';

// Define the lottery types to be displayed
const LOTTERY_TYPES = [
  '香港六合彩',
  '新澳门六合彩',
  '老澳门六合彩'
];

const NumberBall = ({ number, color }) => (
  <div className={`number-ball ${color || ''}`}>
    {number}
  </div>
);

// A single banner component for one lottery type
const LotteryResultBanner = ({ type, result, error }) => {
  if (error) {
    return (
      <div className="lottery-banner error">
        <span className="lottery-name">{type}</span>
        <span className="lottery-error-message">未能加载</span>
      </div>
    );
  }

  if (!result) {
    return (
      <div className="lottery-banner loading">
        <span className="lottery-name">{type}</span>
        <span>加载中...</span>
      </div>
    );
  }

  const numberColorMap = result.number_colors_json ? JSON.parse(result.number_colors_json) : {};
  const winningNumbers = result.winning_numbers ? result.winning_numbers.split(',') : [];

  return (
    <div className="lottery-banner">
      <span className="lottery-name">{type}</span>
      <div className="winning-numbers-container">
        {winningNumbers.map(num => (
          <NumberBall key={num} number={num} color={numberColorMap[num]} />
        ))}
      </div>
    </div>
  );
};


const LotteryPage = () => {
  const [results, setResults] = useState({});
  const [loading, setLoading] = useState(true);

  const fetchAllLotteryData = async () => {
    setLoading(true);

    const promises = LOTTERY_TYPES.map(type =>
      fetch(`/get_lottery_results.php?lottery_type=${encodeURIComponent(type)}&limit=1`) // Fetch only the latest result
        .then(response => {
          if (!response.ok) {
            throw new Error(`网络错误 (状态: ${response.status})`);
          }
          return response.json();
        })
        .then(data => {
          if (data && data.status === 'success' && data.lottery_results.length > 0) {
            return { type, data: data.lottery_results[0], error: null };
          }
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

  return (
    <div className="page-container">
      <h1 className="page-title">最新开奖</h1>
      <div className="lottery-banners-container">
        {LOTTERY_TYPES.map(type => {
          const resultItem = results[type];
          return <LotteryResultBanner key={type} type={type} result={resultItem?.data} error={resultItem?.error} />;
        })}
      </div>
      <footer className="page-footer">
        <p>请以官方开奖结果为准</p>
      </footer>
    </div>
  );
};

export default LotteryPage;