// frontend/src/pages/Home.tsx
import React, { useState } from 'react';
import LotteryBanner from '../components/LotteryBanner';

const dummyDraws = {
  '新澳': { lottery_type: '新澳', period: '2024001', winning_numbers: '01,02,03,04,05,06', draw_time: '2024-01-01 21:30:00' },
  '老澳': { lottery_type: '老澳', period: '2024001', winning_numbers: '07,08,09,10,11,12', draw_time: '2024-01-01 21:30:00' },
  '港彩': { lottery_type: '港彩', period: '2024001', winning_numbers: '13,14,15,16,17,18', draw_time: '2024-01-01 21:30:00' },
};

const Home: React.FC = () => {
  const [betText, setBetText] = useState('');
  const [resultText, setResultText] = useState('');

  const handleSettle = () => {
    const lines = betText.split('\n');
    let results = '';

    lines.forEach(line => {
      const parts = line.split(' ');
      if (parts.length < 2) return;

      const lotteryType = parts[0];
      const betNumbers = parts.slice(1);
      const draw = dummyDraws[lotteryType as keyof typeof dummyDraws];

      if (draw) {
        const winningNumbers = draw.winning_numbers.split(',');
        const matchedNumbers = betNumbers.filter(num => winningNumbers.includes(num));
        const winnings = matchedNumbers.length * 10; // Simple placeholder logic
        results += `${line} -> 匹配: ${matchedNumbers.length}, 奖金: ${winnings}\n`;
      }
    });

    setResultText(results);
  };

  return (
    <div className="container">
      <h1 className="title">六合彩结算</h1>
      <div className="banner-container">
        {Object.values(dummyDraws).map((lottery) => (
          <LotteryBanner key={lottery.lottery_type} lottery={lottery} />
        ))}
      </div>
      <div>
        <textarea
          className="text-area"
          placeholder="在这里输入投注内容..."
          value={betText}
          onChange={(e) => setBetText(e.target.value)}
        />
      </div>
      <div>
        <textarea
          className="text-area"
          placeholder="结算结果将显示在这里..."
          value={resultText}
          readOnly
        />
        <button className="button" onClick={() => navigator.clipboard.writeText(resultText)}>
          复制结果
        </button>
        <button className="button" onClick={handleSettle} style={{ marginLeft: '10px' }}>
          结算
        </button>
      </div>
    </div>
  );
};

export default Home;
