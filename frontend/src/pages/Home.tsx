// frontend/src/pages/Home.tsx
import React, { useState, useEffect } from 'react';
import { getLatestDraw } from '../api';

interface DrawData {
  period: string;
  winning_numbers: string;
  draw_time: string;
}

const Home: React.FC = () => {
  const [latestDraw, setLatestDraw] = useState<DrawData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchDraw = async () => {
      try {
        setLoading(true);
        const response = await getLatestDraw();
        if (response.data.success) {
          setLatestDraw(response.data.data);
        } else {
          setError(response.data.message);
        }
      } catch (err) {
        setError('无法连接到服务器');
      } finally {
        setLoading(false);
      }
    };

    fetchDraw();
  }, []);

  return (
    <div>
      <h1>六合彩模拟投注</h1>
      <h2>最新开奖结果</h2>
      {loading && <p>加载中...</p>}
      {error && <p style={{ color: 'red' }}>错误: {error}</p>}
      {latestDraw && (
        <div>
          <p><strong>期号:</strong> {latestDraw.period}</p>
          <p><strong>开奖号码:</strong> {latestDraw.winning_numbers}</p>
          <p><strong>时间:</strong> {latestDraw.draw_time}</p>
        </div>
      )}
      {/* 在这里添加投注面板等其他组件 */}
    </div>
  );
};

export default Home;
