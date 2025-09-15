// frontend/src/pages/Home.tsx
import React, { useState } from 'react';
import LotteryBanner from '../components/LotteryBanner';

const dummyDraws = {
  'Xin Ao': { lottery_type: 'Xin Ao', period: '2024001', winning_numbers: '01,02,03,04,05,06', draw_time: '2024-01-01 21:30:00' },
  'Lao Ao': { lottery_type: 'Lao Ao', period: '2024001', winning_numbers: '07,08,09,10,11,12', draw_time: '2024-01-01 21:30:00' },
  'Gang Cai': { lottery_type: 'Gang Cai', period: '2024001', winning_numbers: '13,14,15,16,17,18', draw_time: '2024-01-01 21:30:00' },
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
        results += `${line} -> Matched: ${matchedNumbers.length}, Winnings: ${winnings}\n`;
      }
    });

    setResultText(results);
  };

  return (
    <div style={{ padding: '20px', maxWidth: '800px', margin: '0 auto' }}>
      <h1 style={{ textAlign: 'center' }}>六合彩结算</h1>
      <div style={{ display: 'flex', justifyContent: 'space-around', marginBottom: '20px' }}>
        {Object.values(dummyDraws).map((lottery) => (
          <LotteryBanner key={lottery.lottery_type} lottery={lottery} />
        ))}
      </div>
      <div>
        <textarea
          style={{ width: '100%', height: '200px', marginBottom: '10px' }}
          placeholder="在这里输入投注内容..."
          value={betText}
          onChange={(e) => setBetText(e.target.value)}
        />
      </div>
      <div>
        <textarea
          style={{ width: '100%', height: '200px', marginBottom: '10px' }}
          placeholder="结算结果将显示在这里..."
          value={resultText}
          readOnly
        />
        <button onClick={() => navigator.clipboard.writeText(resultText)}>
          复制结果
        </button>
        <button onClick={handleSettle} style={{ marginLeft: '10px' }}>
          结算
        </button>
      </div>
    </div>
  );
};

export default Home;
