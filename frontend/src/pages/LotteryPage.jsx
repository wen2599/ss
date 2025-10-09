import React, { useState, useEffect } from 'react';
import './LotteryPage.css';

const LotteryPage = () => {
  const [lotteryData, setLotteryData] = useState({
    winning_numbers: '加载中...', // Use winning_numbers, provide user-friendly loading text
    created_at: 'N/A',
  });
  const [error, setError] = useState(null);

  const fetchData = () => {
    setError(null);
    fetch('/api/getLotteryNumber') // This path is proxied by the worker
      .then(async (response) => {
        if (!response.ok) {
          const errorData = await response.json().catch(() => ({ message: 'An unknown error occurred.' }));
          // The default error response from the API also uses winning_numbers
          throw new Error(errorData.winning_numbers || `HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        // Check for the expected property to ensure data is valid
        if (data && data.winning_numbers) {
            setLotteryData(data);
        } else {
            // This can happen if the API returns valid JSON but not the expected structure
            throw new Error('Invalid data format received from server.');
        }
      })
      .catch((error) => {
        console.error('Error fetching lottery data:', error);
        setError(error.message);
        // Set a user-friendly error state
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
            {/* Render the correct property from state */}
            <p className="lottery-number">{lotteryData.winning_numbers}</p>
            {/* Render the correct timestamp property */}
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