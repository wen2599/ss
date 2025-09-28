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

  // Define which lotteries get a special stamp and their display names
  const stampedLotteries = {
    '香港': '香港',
    '老澳门': '老澳',
    '新澳门': '新澳'
  };

  const showStamp = Object.keys(stampedLotteries).includes(lottery_name);
  const displayName = stampedLotteries[lottery_name];

  return (
    <div className="lottery-banner">
      {showStamp && <div className="lottery-name-stamp">{displayName}</div>}
      <div className="lottery-details">
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
    </div>
  );
}

export default LotteryBanner;
