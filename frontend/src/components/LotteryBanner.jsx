import React from 'react';
import './LotteryBanner.css';

/**
 * A helper function to derive lottery display information from its name.
 * @param {string} name The full name of the lottery.
 * @returns {{name: string, class: string} | null} An object with a short name and CSS class, or null.
 */
const getLotteryInfo = (name) => {
  if (name.includes('香港')) return { name: '香港', class: 'hk' };
  if (name.includes('老澳')) return { name: '老澳', class: 'om' };
  if (name.includes('新澳门')) return { name: '新澳', class: 'nm' };
  return null;
};

/**
 * Renders a single lottery number ball for the banner.
 * @param {{
 *   num: string,
 *   idx: number,
 *   getNumberColorClass: (num: string) => string
 * }} props
 */
const BannerNumber = ({ num, idx, getNumberColorClass }) => (
  <span className={`${getNumberColorClass(num)}${idx === 6 ? ' special-number' : ''}`}>
    {num}
  </span>
);

/**
 * A banner component to display the latest result for a single lottery.
 * It features a colored theme based on the lottery type.
 *
 * @param {{
 *   latestResult: object | null,
 *   getNumberColorClass: (num: string) => string
 * }} props
 */
function LotteryBanner({ latestResult, getNumberColorClass }) {
  if (!latestResult || !latestResult.numbers) {
    return null;
  }

  const { lottery_name, issue_number, numbers } = latestResult;
  const numberArray = numbers.split(',');
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
            <BannerNumber key={idx} num={num} idx={idx} getNumberColorClass={getNumberColorClass} />
          ))}
        </div>
      </div>
    </div>
  );
}

export default LotteryBanner;