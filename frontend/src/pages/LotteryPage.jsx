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

const LotteryTabs = ({ types, activeType, onTabClick }) => (
  <div className="lottery-tabs">
    {types.map(type => (
      <button
        key={type}
        className={`tab-button ${activeType === type ? 'active' : ''}`}
        onClick={() => onTabClick(type)}
      >
        {type}
      </button>
    ))}
  </div>
);

const LotteryResultDisplay = ({ data }) => {
  if (!data) {
    return (
      <div className="lottery-placeholder">
        <h3>等待开奖</h3>
        <p>该类型暂无最新开奖数据，请稍后刷新。</p>
      </div>
    );
  }

  return (
    <>
      <div className="lottery-header">
        <h2>{data.lottery_type}</h2>
        <p className="issue">第 {data.issue} 期</p>
      </div>
      <div className="lottery-results">
        {data.numbers.map((number, index) => (
          <div key={index} className={`lottery-ball ${getBallColorClass(data.colors[index])}`}>
            <span className="ball-number">{number}</span>
            <span className="ball-zodiac">{data.zodiacs[index]}</span>
          </div>
        ))}
      </div>
    </>
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
  const [allLotteryData, setAllLotteryData] = useState({});
  const [activeTab, setActiveTab] = useState('');
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
        // Set the first available lottery type as the active tab
        const firstType = Object.keys(data)[0];
        if (firstType) {
          setActiveTab(firstType);
        }
      })
      .catch(err => {
        setError(err.message);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) {
    return <div className="card lottery-container"><LotteryLoading /></div>;
  }
  
  if (error) {
    return <div className="card lottery-container"><LotteryError message={error} /></div>;
  }

  const lotteryTypes = Object.keys(allLotteryData);
  if (lotteryTypes.length === 0) {
    return <div className="card lottery-container"><LotteryError message="未能获取到任何开奖数据类型。" /></div>;
  }

  return (
    <div className="card lottery-container">
      <LotteryTabs
        types={lotteryTypes}
        activeType={activeTab}
        onTabClick={setActiveTab}
      />
      <div className="lottery-content">
        <LotteryResultDisplay data={allLotteryData[activeTab]} />
      </div>
    </div>
  );
}

export default LotteryPage;
