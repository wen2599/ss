
import React, { useState, useEffect } from 'react';
import './App.css'; // We will add some styling

function App() {
  const [lotteryData, setLotteryData] = useState({
    lottery_number: 'Loading...',
    received_at_utc: 'N/A',
  });
  const [error, setError] = useState(null);

  const fetchData = () => {
    setError(null); // Clear previous errors
    fetch('/api/getLotteryNumber')
      .then(async (response) => {
        if (!response.ok) {
          // Handle cases where the file doesn't exist yet (404) or other errors
          const errorData = await response.json().catch(() => ({ message: 'An unknown error occurred.' }));
          throw new Error(errorData.lottery_number || `HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        setLotteryData(data);
      })
      .catch((error) => {
        console.error('Error fetching lottery data:', error);
        setError(error.message);
        setLotteryData({ lottery_number: 'Error', received_at_utc: '-' });
      });
  };

  useEffect(() => {
    fetchData(); // Fetch immediately on component mount

    // Set up an interval to fetch data every 15 seconds
    const intervalId = setInterval(fetchData, 15000);

    // Clear the interval when the component unmounts
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
            <p className="lottery-number">{lotteryData.lottery_number}</p>
            <p className="timestamp">最后更新: {lotteryData.received_at_utc}</p>
          </div>
        )}
      </main>
      <footer className="footer">
        <p>请以官方开奖结果为准</p>
      </footer>
    </div>
  );
}

export default App;
