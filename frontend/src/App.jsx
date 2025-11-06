import React, { useState, useEffect } from 'react';
import './App.css';

function App() {
  const [lotteryData, setLotteryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchLotteryNumber = async () => {
      setLoading(true);
      setError(null);
      try {
        // 请求我们自己的 /api 路径，Cloudflare Worker会将其代理到后端
        const response = await fetch('/api/data'); // 路径可以是任意 /api/* 开头的
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();

        if (data.success) {
          setLotteryData(data.data);
        } else {
          throw new Error(data.message || 'Failed to fetch data.');
        }

      } catch (e) {
        console.error("Fetch error:", e);
        setError(e.message);
      } finally {
        setLoading(false);
      }
    };

    fetchLotteryNumber();

    // 设置一个定时器，例如每分钟刷新一次数据
    const intervalId = setInterval(fetchLotteryNumber, 60000); // 60秒

    // 组件卸载时清除定时器
    return () => clearInterval(intervalId);
  }, []); // 空依赖数组表示只在组件挂载时运行一次初始 fetch

  return (
    <div className="container">
      <header>
        <h1>最新开奖号码</h1>
      </header>
      <main>
        <div className="card">
          {loading && <p className="status">正在加载...</p>}
          {error && <p className="status error">加载失败: {error}</p>}
          {lotteryData && (
            <div className="result">
              <span className="number-label">开奖号码:</span>
              <span className="number">{lotteryData.number}</span>
              <span className="time">开奖时间: {new Date(lotteryData.time).toLocaleString()}</span>
            </div>
          )}
          {!loading && !lotteryData && !error && (
             <p className="status">暂无开奖数据</p>
          )}
        </div>
      </main>
      <footer>
        <p>数据自动更新</p>
      </footer>
    </div>
  );
}

export default App;