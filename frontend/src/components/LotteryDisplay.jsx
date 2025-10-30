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
        const response = await api.get('/api.php?action=get_latest_lottery');
        setResult(response.data);
        setError('');
      } catch (err) {
        if (err.response && err.response.status === 404) {
             setError('暂无开奖结果。');
        } else {
             setError('无法加载结果，请稍后再试。');
        }
        console.error("LotteryDisplay Error:", err);
      } finally {
        setLoading(false);
      }
    };
    fetchLatestResult();
  }, []);

  if (loading) return <p className="loading">正在加载...</p>;
  if (error) return <p className="error">{error}</p>; 
  if (!result) return <p>暂无开奖结果。</p>;

  const winningNumbers = result.winning_numbers ? String(result.winning_numbers).split(',') : [];

  return (
    <div className="lottery-container">
      <h2>最新开奖结果</h2>
      <p>期号: {result.issue_number || 'N/A'}</p>
      <div className="lottery-numbers">
        {winningNumbers.map((num, index) => (<span key={index}>{num.trim()}</span>))}
        {winningNumbers.length > 0 && <span>+</span>}
        <span className="special">{result.special_number || 'N/A'}</span>
      </div>
    </div>
  );
};

export default LotteryDisplay;