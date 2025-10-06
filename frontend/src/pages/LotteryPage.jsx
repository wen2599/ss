import { useState, useEffect } from 'react';
import './LotteryPage.css';

// --- Helper & Sub-components ---

const getBallColorClass = (color) => {
  switch (color?.toLowerCase()) {
    case 'red': return 'ball-red';
    case 'blue': return 'ball-blue';
    case 'green': return 'ball-green';
    default: return '';
  }
};

const LotteryBanner = ({ lotteryType, data }) => {
  return (
    <div className="lottery-banner">
      <div className="lottery-header">
        <h2>{lotteryType}</h2>
        {data ? (
          <p className="issue">第 {data.issue} 期</p>
        ) : (
          <p className="issue">等待开奖</p>
        )}
      </div>
      {data ? (
        <div className="lottery-results">
          {data.numbers.map((number, index) => (
            <div key={index} className={`lottery-ball ${getBallColorClass(data.colors[index])}`}>
              <span className="ball-number">{number}</span>
              <span className="ball-zodiac">{data.zodiacs[index]}</span>
            </div>
          ))}
        </div>
      ) : (
        <div className="lottery-placeholder-small">
          <p>暂无最新开奖数据</p>
        </div>
      )}
    </div>
  );
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

// --- Main Component ---

function LotteryPage() {
  const [allLotteryData, setAllLotteryData] = useState(null);
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
        setAllLotteryData(data);
      })
      .catch(err => {
        setError(err.message);
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

  if (!allLotteryData) {
    return <LotteryError message="未能获取到任何开奖数据。" />;
  }

  const lotteryTypes = Object.keys(allLotteryData);

  return (
    <div className="lottery-container">
      {lotteryTypes.map(type => (
        <LotteryBanner key={type} lotteryType={type} data={allLotteryData[type]} />
      ))}
    </div>
  );
}

export default LotteryPage;
