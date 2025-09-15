// frontend/src/pages/Settlement.tsx
import React from 'react';
import LotteryBanner from '../components/LotteryBanner';

const dummyDraws = {
  'Xin Ao': { lottery_type: 'Xin Ao', period: '2024001', winning_numbers: '01,02,03,04,05,06', draw_time: '2024-01-01 21:30:00' },
  'Lao Ao': { lottery_type: 'Lao Ao', period: '2024001', winning_numbers: '07,08,09,10,11,12', draw_time: '2024-01-01 21:30:00' },
  'Gang Cai': { lottery_type: 'Gang Cai', period: '2024001', winning_numbers: '13,14,15,16,17,18', draw_time: '2024-01-01 21:30:00' },
};

const Settlement: React.FC = () => {
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
        />
      </div>
      <div>
        <textarea
          style={{ width: '100%', height: '200px', marginBottom: '10px' }}
          placeholder="结算结果将显示在这里..."
          readOnly
        />
        <button onClick={() => navigator.clipboard.writeText('dummy results')}>
          复制结果
        </button>
      </div>
    </div>
  );
};

export default Settlement;
