// src/pages/LotteryPage.jsx
import React, { useState, useEffect } from 'react';
import api from '../services/api';
import './LotteryPage.css'; // 为页面添加一些样式

const LotteryPage = () => {
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchResults = async () => {
      try {
        setLoading(true);
        setError('');
        // 请求代理到后端的 get_lottery_results action
        const response = await api.get('/proxy.php?action=get_lottery_results');
        if (response.data.status === 'success') {
          setResults(response.data.data);
        } else {
          setError(response.data.message || '获取数据失败');
        }
      } catch (err) {
        setError(err.response?.data?.message || '网络错误，请稍后再试');
      } finally {
        setLoading(false);
      }
    };

    fetchResults();
  }, []); // 空依赖数组意味着这个effect只在组件挂载时运行一次

  // 格式化开奖号码，将特码高亮
  const formatNumbers = (numbersStr) => {
    const parts = numbersStr.split(',');
    const specialNumber = parts.pop();
    return (
      <>
        {parts.join(', ')}
        <span className="special-number">, {specialNumber}</span>
      </>
    );
  };

  if (loading) {
    return <div>正在加载最新开奖数据...</div>;
  }

  if (error) {
    return <div className="error-message">{error}</div>;
  }

  return (
    <div className="lottery-container">
      <h1>历史开奖记录</h1>
      <table className="lottery-table">
        <thead>
          <tr>
            <th>期号</th>
            <th>开奖日期</th>
            <th>开奖号码</th>
          </tr>
        </thead>
        <tbody>
          {results.length > 0 ? (
            results.map((result) => (
              <tr key={result.issue_number}>
                <td>{result.issue_number}</td>
                <td>{result.draw_date}</td>
                <td>{formatNumbers(result.numbers)}</td>
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan="3">暂无开奖数据</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
};

export default LotteryPage;