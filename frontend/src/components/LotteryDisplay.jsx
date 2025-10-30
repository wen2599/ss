// 文件名: LotteryDisplay.jsx
// 路径: frontend/src/components/LotteryDisplay.jsx
// 版本: Final - Single Entry Point

import React, { useState, useEffect } from 'react';
import api from '../services/api';

const LotteryDisplay = () => {
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchLatestResult = async () => {
      try {
        setLoading(true);
        
        // --- 关键修改在这里 ---
        // 请求根目录下的 api.php, 并通过 'action' 参数告诉它我们想要做什么
        const response = await api.get('/api.php?action=get_latest_result');
        // --- 修改结束 ---
        
        setResult(response.data);
        setError('');
      } catch (err) {
        if (err.response && err.response.status === 404) {
             setError('暂无开奖结果。');
        } else {
             setError('无法加载最新的开奖结果，请稍后再试。');
        }
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchLatestResult();
  }, []);

  if (loading) return <p className="loading">正在加载开奖结果...</p>;
  if (error) return <p>{error}</p>; 
  if (!result) return <p>暂无开奖结果。</p>;

  const winningNumbers = result.winning_numbers && typeof result.winning_numbers === 'string' 
    ? result.winning_numbers.split(',') 
    : [];

  return (
    <div className="lottery-container">
      <h2>最新开奖结果</h2>
      <p>期号: {result.issue_number}</p>
      <p>开奖日期: {result.draw_date}</p>
      <div className="lottery-numbers">
        {winningNumbers.map((num, index) => (
          <span key={index}>{num.trim()}</span>
        ))}
        {winningNumbers.length > 0 && <span>+</span>}
        <span className="special">{result.special_number}</span>
      </div>
    </div>
  );
};

export default LotteryDisplay;