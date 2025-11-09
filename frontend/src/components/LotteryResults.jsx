// src/components/LotteryResults.jsx (Banner Version)
import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

// 定义彩票类型和它们的顺序
const LOTTERY_TYPES = ['香港六合彩', '新澳门六合彩', '老澳门六合彩'];

// 波色文字到 CSS 类名的映射
const colorClassMap = {
  '红波': 'color-red',
  '绿波': 'color-green',
  '蓝波': 'color-blue',
};

// 单个开奖号码格子的组件
const NumberCell = ({ number, color }) => {
  const bgColorClass = colorClassMap[color] || 'color-unknown';
  return (
    <div className={`number-cell ${bgColorClass}`}>
      {number}
    </div>
  );
};

// 单个横幅的组件
const LotteryBanner = ({ type, data }) => {
  if (!data) {
    return (
      <div className="lottery-banner">
        <div className="banner-header">
          <h3>{type}</h3>
          <p>等待开奖...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="lottery-banner">
      <div className="banner-header">
        <h3>{type}</h3>
        <p>第 {data.issue_number} 期 - {data.drawing_date}</p>
      </div>
      <div className="numbers-grid">
        {data.winning_numbers.map((number, index) => (
          <NumberCell key={index} number={number} color={data.colors[index]} />
        ))}
      </div>
    </div>
  );
};


// 主组件
function LotteryResults() {
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchData = () => {
        apiService.getLotteryResults()
        .then(response => {
          if (response.status === 'success') {
            setResults(response.data);
          } else {
            setError(response.message || 'Failed to load data');
          }
        })
        .catch(err => setError(err.message))
        .finally(() => setLoading(false));
    };
    
    fetchData();
    // 设置一个定时器，每30秒自动刷新一次数据
    const intervalId = setInterval(fetchData, 30000); 

    // 组件卸载时清除定时器
    return () => clearInterval(intervalId);
  }, []);

  if (loading) return <p>正在加载开奖记录...</p>;
  if (error) return <p style={{ color: 'red' }}>错误: {error}</p>;
  if (!results) return <p>无法加载数据结构。</p>;

  return (
    <div className="lottery-banners-container">
      {LOTTERY_TYPES.map(type => (
        <LotteryBanner key={type} type={type} data={results[type]} />
      ))}
    </div>
  );
}

export default LotteryResults;