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

  const renderContent = () => {
    if (loading) {
      return <div className="status-message">正在从服务器获取最新数据，请稍候...</div>;
    }
    if (error) {
      return <div className="status-message error">数据加载失败: {error}. 请检查您的网络连接或稍后再试。</div>;
    }
    if (message) {
      return <div className="status-message">{message}</div>;
    }
    if (lotteryData) {
      return (
        <div className="result-grid">
          <div className="grid-item label">彩种</div>
          <div className="grid-item value">{lotteryData.lottery_type}</div>

          <div className="grid-item label">期号</div>
          <div className="grid-item value">{lotteryData.issue_number}</div>

          <div className="grid-item label">开奖号码</div>
          <div className="grid-item value numbers">{lotteryData.numbers}</div>

          <div className="grid-item label">开奖时间</div>
          <div className="grid-item value">{new Date(lotteryData.draw_time).toLocaleString('zh-CN')}</div>
        </div>
      );
    }
    return <div className="status-message">暂无开奖数据。</div>;
  };

  return (
    <div className="container">
      <header className="app-header">
        <h1>最新开奖结果</h1>
      </header>
      <main>
        <div className="card">
          {renderContent()}
        </div>
      </main>
      <footer className="app-footer">
        <p>数据每分钟自动刷新</p>
      </footer>
    </div>
  );
}

export default HomePage;