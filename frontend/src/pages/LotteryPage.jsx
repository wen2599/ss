import React, { useEffect, useState } from 'react';
import { getLotteryResults } from '../api';
import './LotteryPage.css';

const LotteryPage = () => {
  const [lotteryResults, setLotteryResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchLotteryResults = async () => {
      try {
        setLoading(true);
        const data = await getLotteryResults();
        if (data.success) {
          setLotteryResults(data.lotteryResults);
        } else {
          setError(data.error || '获取开奖结果失败。');
        }
      } catch (err) {
        setError(err.message || '获取开奖结果时发生错误。');
      } finally {
        setLoading(false);
      }
    };

    fetchLotteryResults();
  }, []);

  if (loading) return <div>正在加载开奖结果...</div>;
  if (error) return <div className="alert error">错误：{error}</div>;

  return (
    <div className="lottery-page">
      <h1>最新开奖结果</h1>
      {lotteryResults.length === 0 ? (
        <p>暂无开奖结果。</p>
      ) : (
        <table className="lottery-table">
          <thead>
            <tr>
              <th>开奖日期</th>
              <th>中奖号码</th>
            </tr>
          </thead>
          <tbody>
            {lotteryResults.map((result) => (
              <tr key={result.id}>
                <td>{new Date(result.draw_date).toLocaleDateString()}</td>
                <td>{result.winning_numbers}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default LotteryPage;
