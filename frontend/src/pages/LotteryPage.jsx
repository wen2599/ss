import { useState, useEffect } from 'react';
import './LotteryPage.css';

// --- Re-usable Sub-components ---

const LotteryCard = ({ title, data }) => {
  const colorClassMap = { '🔴': 'color-red', '🟢': 'color-green', '🔵': 'color-blue' };

  const renderResults = () => {
    if (!data) {
      return <div className="placeholder"><p>暂无最新开奖数据</p></div>;
    }

    const numbersHtml = data.numbers.map((num, i) =>
      <div key={i} className={`number-ball ${colorClassMap[data.colors[i]] || ''}`}>{num}</div>);
    const zodiacsHtml = data.zodiacs.map((z, i) => <div key={i} className="zodiac">{z}</div>);
    const colorsHtml = data.colors.map((c, i) => <div key={i} className="color-emoji">{c}</div>);

    return (
      <div className="results-grid">
        <div className="result-row">{numbersHtml}</div>
        <div className="result-row">{zodiacsHtml}</div>
        <div className="result-row">{colorsHtml}</div>
      </div>
    );
  };

  return (
    <div className="lottery-card">
      <div className="card-header">
        <h2 className="lottery-title">{title}</h2>
        {data && <p className="lottery-issue">第 {data.issue} 期</p>}
      </div>
      {renderResults()}
    </div>
  );
};

const LotteryLoading = () => (
  <div className="lottery-container">
    <div className="lottery-card placeholder">
      <h3>正在从宇宙深处同步数据...</h3>
      <p>请稍候，结果即将呈现。</p>
    </div>
  </div>
);

const LotteryError = ({ message }) => (
  <div className="lottery-container">
    <div className="lottery-card placeholder error-message">
      <h3>数据同步失败</h3>
      <p>{message}</p>
    </div>
  </div>
);

// --- Main Component ---

function LotteryPage() {
  const [allLotteryData, setAllLotteryData] = useState({});
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);

  // Define the desired display order
  const displayOrder = ['新澳门六合彩', '香港六合彩', '老澳21.30'];

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

  return (
    <div className="lottery-container">
      {displayOrder.map(type => (
        <LotteryCard key={type} title={type} data={allLotteryData[type]} />
      ))}
    </div>
  );
}

export default LotteryPage;