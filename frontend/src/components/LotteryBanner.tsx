// frontend/src/components/LotteryBanner.tsx
import React from 'react';

interface Lottery {
  lottery_type: string;
  winning_numbers: string;
}

interface LotteryBannerProps {
  lottery: Lottery;
}

const LotteryBanner: React.FC<LotteryBannerProps> = ({ lottery }) => {
  return (
    <div className="banner">
      <h3 style={{ marginRight: '20px' }}>{lottery.lottery_type}</h3>
      <p>{lottery.winning_numbers}</p>
    </div>
  );
};

export default LotteryBanner;
