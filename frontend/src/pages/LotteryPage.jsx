import React, { useState, useEffect } from 'react';
import './LotteryPage.css';

const LotteryPage = () => {
  const [lotteryData, setLotteryData] = useState({
    winning_numbers: '加载中...', // Corrected field name and translated text
    created_at: 'N/A',         // Corrected field name
  });
  const [error, setError] = useState(null);

  const fetchData = () => {
    setError(null);
    fetch('/api/getLotteryNumber')
      .then(async (response) => {
        if (!response.ok) {
          const errorData = await response.json().catch(() => ({ message: '发生未知错误。' }));
          // Use winning_numbers for error message consistency
          throw new Error(errorData.winning_numbers || `HTTP 错误！状态: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        // Ensure data has the expected properties before setting state
        if (data && data.winning_numbers) {
            setLotteryData(data);
        } else {
            // Handle cases where API returns unexpected JSON structure
            throw new Error('从服务器收到的数据格式无效。');
        }
      })
      .catch((error) => {
        console.error('获取开奖数据时出错:', error);
        setError(error.message);
        setLotteryData({ winning_numbers: '获取失败', created_at: '-' });
      });
  };

  useEffect(() => {
    fetchData();
    const intervalId = setInterval(fetchData, 15000);
    return () => clearInterval(intervalId);
  }, []);

  return (
    <div className="container">
      <header className="header">
        <h1>最新开奖号码</h1>
      </header>
      <main className="main-content">
        {error ? (
          <div className="card error-card">
            <h2>数据加载失败</h2>
            <p>{error}</p>
            <button onClick={fetchData}>重试</button>
          </div>
        ) : (
          <div className="card lottery-card">
            {/* Render the correct properties */}
            <p className="lottery-number">{lotteryData.winning_numbers}</p>
            <p className="timestamp">最后更新: {lotteryData.created_at}</p>
          </div>
        )}
      </main>
      <footer className="footer">
        <p>请以官方开奖结果为准</p>
      </footer>
    </div>
  );
};

export default LotteryPage;