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

  const getLotteryInfo = (name) => {
    if (name.includes('香港')) {
      return { name: '香港', class: 'hk' };
    }
    if (name.includes('老澳')) {
      return { name: '老澳', class: 'om' };
    }
    if (name.includes('新澳门')) {
      return { name: '新澳', class: 'nm' };
    }
    return null;
  };

  const lotteryInfo = getLotteryInfo(lottery_name);

  return (
    <div className="lottery-banner">
      {lotteryInfo && (
        <div className={`lottery-name-tag ${lotteryInfo.class}`}>
          {lotteryInfo.name}
        </div>
      )}
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
