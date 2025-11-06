import React, { useState, useEffect } from 'react';
import './App.css';

function App() {
  const [lotteryData, setLotteryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  // 新增一个状态，用于显示来自API的友好提示信息
  const [message, setMessage] = useState('');

  useEffect(() => {
    const fetchLotteryNumber = async () => {
      setLoading(true);
      setError(null);
      setMessage(''); // 每次请求前重置消息

      try {
        const response = await fetch('/api/data'); 
        
        if (!response.ok) {
          // 处理真正的网络或服务器错误 (如 500, 502 等)
          throw new Error(`服务器响应错误，状态码: ${response.status}`);
        }
        
        const data = await response.json();

        // --- 核心修改在这里 ---
        if (data.success) {
          // API 返回成功，且有数据
          setLotteryData(data.data);
        } else {
          // API 返回成功，但业务上失败 (例如没有数据)
          // 我们不再抛出错误，而是设置提示信息
          setLotteryData(null); // 确保没有旧数据显示
          setMessage(data.message || '暂无数据');
        }
        // --- 修改结束 ---

      } catch (e) {
        console.error("Fetch error:", e);
        // 只有在捕获到真正的异常时，才设置错误状态
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
        <h1>最新开奖号码</h1>
      </header>
      <main>
        <div className="card">
          {loading && <p className="status">正在加载...</p>}
          
          {/* 显示真正的错误 */}
          {error && <p className="status error">加载失败: {error}</p>}
          
          {/* 显示来自API的友好提示 (例如 "No lottery numbers found.") */}
          {!loading && !error && message && <p className="status">{message}</p>}

          {/* 成功获取并显示数据 */}
          {!loading && !error && lotteryData && (
            <div className="result">
              <span className="number-label">开奖号码:</span>
              <span className="number">{lotteryData.number}</span>
              {/* 检查 time 字段是否存在，避免 undefined 错误 */}
              <span className="time">
                开奖时间: {lotteryData.time ? new Date(lotteryData.time).toLocaleString() : 'N/A'}
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

export default App;