import { useState, useEffect } from 'react';

// 辅助函数保持不变
const getBallColorClass = (color) => {
  switch (color.toLowerCase()) {
    case 'red': return 'ball-red';
    case 'blue': return 'ball-blue';
    case 'green': return 'ball-green';
    default: return '';
  }
};

function LotteryPage() {
  const [lotteryData, setLotteryData] = useState(null);
  const [error, setError] = useState(null);

  // 获取开奖数据
  useEffect(() => {
    fetch('/get_numbers')
      .then(response => {
        if (!response.ok) throw new Error('网络响应不正常');
        return response.json();
      })
      .then(data => {
        if (data.error) throw new Error(data.error);
        setLotteryData(data);
      })
      .catch(error => {
        setError(error.message);
      });
  }, []);

  return (
    <>
      {error && <p className="error">错误: {error}</p>}
      {lotteryData ? (
        <div className="lottery-card">
          <h2>{lotteryData.lottery_type || '新澳门六合彩'}</h2>
          <p className="issue-info">期号: {lotteryData.issue}</p>
          <div className="results-grid">
            {lotteryData.numbers.map((number, index) => (
              <div 
                key={index} 
                className={`number-ball ${getBallColorClass(lotteryData.colors[index])}`}
              >
                {number}
                <span className="zodiac-sign">{lotteryData.zodiacs[index]}</span>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <p>正在从宇宙深处获取开奖数据...</p>
      )}
    </>
  );
}

export default LotteryPage;
