import React from 'react';

/**
 * 最新开奖号码横幅，带球颜色和渐变主题
 * 依赖 getNumberColorClass 返回如 number-ball number-ball-red 等 className
 */
function LotteryBanner({ latestResult, getNumberColorClass }) {
  if (!latestResult) {
    return null;
  }

  const { lottery_name, issue_number, numbers } = latestResult;
  const numberArray = numbers.split(',');

  return (
    <div className="lottery-banner">
      <h3>最新开奖: {lottery_name} - 第 {issue_number} 期</h3>
      <div className="banner-numbers">
        {numberArray.map((num, idx) => (
          <span
            key={idx}
            className={
              `${getNumberColorClass(num)}${idx === 6 ? ' special-number' : ''}`
            }
          >
            {num}
          </span>
        ))}
      </div>
    </div>
  );
}

export default LotteryBanner;
