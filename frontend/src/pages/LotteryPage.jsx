import { useState, useEffect } from 'react';
import './LotteryPage.css'; // Import the new styles

const getBallColorClass = (color) => {
  switch (color?.toLowerCase()) {
    case 'red': return 'ball-red';
    case 'blue': return 'ball-blue';
    case 'green': return 'ball-green';
    default: return '';
  }
};

const LotteryLoading = () => (
    <div className="card lottery-placeholder">
        <h3>正在从宇宙深处同步数据...</h3>
        <p>请稍候，结果即将呈现。</p>
    </div>
);

const LotteryError = ({ message }) => (
    <div className="card lottery-placeholder">
        <h3 style={{ color: 'var(--color-danger)' }}>数据同步失败</h3>
        <p className="error" style={{ margin: 0 }}>错误: {message}</p>
    </div>
);

function LotteryPage() {
  const [lotteryData, setLotteryData] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    fetch('/get_numbers')
      .then(response => {
        if (!response.ok) throw new Error('网络响应不正常，请检查您的连接或联系管理员。');
        return response.json();
      })
      .then(data => {
        if (data.error) throw new Error(data.error);
        setLotteryData(data);
      })
      .catch(error => {
        setError(error.message);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) {
    return <LotteryLoading />;
  }
  
  if (error) {
    return <LotteryError message={error} />;
  }

  if (!lotteryData) {
    return <LotteryError message="未能获取到任何开奖数据。" />;
  }

  return (
    <div className="card lottery-container">
      <div className="lottery-header">
        <h2>{lotteryData.lottery_type || '新澳门六合彩'}</h2>
        <p className="issue">第 {lotteryData.issue} 期</p>
      </div>

      <div className="lottery-results">
        {lotteryData.numbers.map((number, index) => (
          <div key={index} className={`lottery-ball ${getBallColorClass(lotteryData.colors[index])}`}>
            <span className="ball-number">{number}</span>
            <span className="ball-zodiac">{lotteryData.zodiacs[index]}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

export default LotteryPage;
