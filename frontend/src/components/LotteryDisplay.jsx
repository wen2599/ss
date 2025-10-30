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
        const response = await api.get('/lottery/get_latest.php');
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