// frontend/src/components/LotteryBanner.tsx
import React from 'react';

interface Lottery {
  lottery_type: string;
  period: string;
  winning_numbers: string;
  draw_time: string;
}

interface LotteryBannerProps {
  lottery: Lottery;
}

const LotteryBanner: React.FC<LotteryBannerProps> = ({ lottery }) => {
  return (
    <div style={{ border: '1px solid #ccc', padding: '10px', marginBottom: '10px' }}>
      <h3>{lottery.lottery_type}</h3>
      <p>Period: {lottery.period}</p>
      <p>Winning Numbers: {lottery.winning_numbers}</p>
      <p>Draw Time: {lottery.draw_time}</p>
    </div>
  );
};

export default LotteryBanner;
