//
// 文件名: LotteryDisplay.jsx
// 路径: frontend/src/components/LotteryDisplay.jsx
// 版本: Final
//

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
        
        // 发起 API 请求
        // 因为 baseURL 是 'https://wenge.cloudns.ch'，
        // 所以这里需要提供从域名根开始的完整路径，即 '/api.php?action=...'
        const response = await api.get('/api.php?action=get_latest_lottery');
        
        setResult(response.data);
        setError('');
      } catch (err) {
        // 优雅地处理错误情况
        if (err.response && err.response.status === 404) {
             // 404 表示后端查询了数据库但没有找到记录，这是正常情况
             setError('暂无开奖结果。');
        } else {
             // 其他错误（如网络问题，500服务器错误等）
             setError('无法加载开奖结果，请稍后再试。');
        }
        console.error("LotteryDisplay Error:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchLatestResult();
  }, []); // 空依赖数组确保此 effect 只在组件首次渲染时执行一次

  if (loading) return <p className="loading">正在加载开奖结果...</p>;
  if (error) return <p className="error">{error}</p>; 
  if (!result) return <p>暂无开奖结果。</p>;

  // 安全地处理可能不存在的 winning_numbers 字段
  const winningNumbers = result.winning_numbers && typeof result.winning_numbers === 'string' 
    ? result.winning_numbers.split(',') 
    : [];

  return (
    <div className="lottery-container">
      <h2>最新开奖结果</h2>
      <p>期号: {result.issue_number}</p>
      <p>中奖号码: {winningNumbers.join(', ')}</p>
      <p>特别号码: {result.special_number}</p>
      <p>开奖日期: {result.draw_date}</p>
    </div>
  );
};

export default LotteryDisplay;
