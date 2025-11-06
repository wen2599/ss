import React, { useState, useEffect } from 'react';

function HomePage() {
  const [lotteryData, setLotteryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState('');

  useEffect(() => {
    const fetchLotteryNumber = async () => {
      setLoading(true);
      setError(null);
      setMessage('');

      try {
        const response = await fetch('/api/?action=get_latest_lottery_result');

        if (!response.ok) {
          throw new Error(`服务器响应状态: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          setLotteryData(data.data);
        } else {
          setLotteryData(null);
          setMessage(data.message || '暂无数据');
        }

      } catch (e) {
        console.error("获取开奖数据时出错:", e);
        setError(e.message);
      } finally {
        setLoading(false);
      }
    };

    fetchLotteryNumber();
    const intervalId = setInterval(fetchLotteryNumber, 60000);
    return () => clearInterval(intervalId);
  }, []);

  return (
    <div className="container">
      <header>
        <h1>最新开奖结果</h1>
      </header>
      <main>
        <div className="card">
          {loading && <p className="status">正在加载...</p>}
          {error && <p className="status error">加载失败: {error}</p>}
          {!loading && !error && message && <p className="status">{message}</p>}
          {!loading && !error && lotteryData && (
            <div className="result">
              <span className="number-label">{lotteryData.lottery_type} ({lotteryData.issue_number}):</span>
              <span className="number">{lotteryData.numbers}</span>
              <span className="time">
                开奖时间: {lotteryData.draw_time ? new Date(lotteryData.draw_time).toLocaleString() : 'N/A'}
              </span>
            </div>
          )}
        </div>
      </main>
      <footer>
        <p>数据每分钟自动刷新</p>
      </footer>
    </div>
  );
}

export default HomePage;