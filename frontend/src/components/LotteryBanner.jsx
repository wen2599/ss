import React from 'react';

/**
 * A component to display the latest lottery result in a banner.
 * @param {object} props - The component props.
 * @param {object} props.latestResult - The latest lottery result object.
 * @param {function} props.getNumberColorClass - A function to get the CSS class for a number's color.
 */
function LotteryBanner({ latestResult, getNumberColorClass }) {
  if (!latestResult) {
    return null; // Don't render anything if there's no result
  }

  const { lottery_name, issue_number, numbers } = latestResult;
  const numberArray = numbers.split(',');

  return (
    <div className="lottery-banner">
      <h3>最新开奖: {lottery_name} - 第 {issue_number} 期</h3>
      <div className="banner-numbers">
        {numberArray.map((num, idx) => (
          <span key={idx} className={`${getNumberColorClass(num)} ${idx === 6 ? 'special-number' : ''}`}>
            {num}
          </span>
        ))}
      </div>
    </div>
  );
}

export default LotteryBanner;
