// 文件名: LotteryDisplay.jsx
// 路径: frontend/src/components/LotteryDisplay.jsx

import React, { useState, useEffect } from 'react';
// 注意：api.js 的导入路径可能需要根据您的项目结构调整
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
        // 旧的请求: api.get('/lottery/get_latest.php')
        // 新的请求: api.get('/get_latest.php') 
        // 因为 baseURL 已经包含了 /data
        const response = await api.get('/get_latest.php');
        // --- 修改结束 ---
        setResult(response.data);
        setError('');
      } catch (err) {
        setError('无法加载最新的开奖结果。');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchLatestResult();
  }, []);

  // ... (文件的其余部分不变) ...
  if (loading) return <p className="loading">正在加载开奖结果...</p>;
  if (error) return <p className="error">{error}</p>;
  if (!result) return <p>暂无开奖结果。</p>;

  return (
    <div className="lottery-container">
      <h2>最新开奖结果</h2>
      <p>期号: {result.issue_number}</p>
      <p>开奖日期: {result.draw_date}</p>
      <div className="lottery-numbers">
        {result.winning_numbers.split(',').map(num => (
          <span key={num}>{num}</span>
        ))}
        <span>+</span>
        <span className="special">{result.special_number}</span>
      </div>
    </div>
  );
};

export default LotteryDisplay;