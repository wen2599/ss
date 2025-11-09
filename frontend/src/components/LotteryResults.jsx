// src/components/LotteryResults.jsx
import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

function LotteryResults() {
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    apiService.getLotteryResults()
      .then(data => {
        if (data.status === 'success') {
          setResults(data.data);
        } else {
          setError(data.message || 'Failed to load data');
        }
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <p>正在加载开奖记录...</p>;
  if (error) return <p style={{ color: 'red' }}>错误: {error}</p>;
  if (results.length === 0) return <p>暂无开奖记录。</p>;

  return (
    <div className="card">
      <h2>开奖记录</h2>
      {results.map(result => (
        <div key={result.id} className="lottery-result" style={{ borderBottom: '1px solid #eee', paddingBottom: '1rem', marginBottom: '1rem' }}>
          <h4>{result.lottery_type} - 第 {result.issue_number} 期</h4>
          <p><strong>开奖日期:</strong> {result.drawing_date}</p>
          <p><strong>号码:</strong> {result.winning_numbers.join(', ')}</p>
          <p><strong>生肖:</strong> {result.zodiac_signs.join(', ')}</p>
          <p><strong>波色:</strong> {result.colors.join(', ')}</p>
        </div>
      ))}
    </div>
  );
}

export default LotteryResults;