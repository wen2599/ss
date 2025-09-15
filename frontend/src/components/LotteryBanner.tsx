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
    <div className="banner">
      <h3>{lottery.lottery_type}</h3>
      <p>期号: {lottery.period}</p>
      <p>开奖号码: {lottery.winning_numbers}</p>
      <p>开奖时间: {lottery.draw_time}</p>
    </div>
  );
};

export default LotteryBanner;
