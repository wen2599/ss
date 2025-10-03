import React from 'react';
import { useLotteryData } from '../hooks/useLotteryData';

/**
 * Renders a single lottery number with the appropriate color styling.
 * @param {{num: string, idx: number, getNumberColorClass: (num: string) => string}} props
 */
const LotteryNumber = ({ num, idx, getNumberColorClass }) => (
  <span className={`${getNumberColorClass(num)} ${idx === 6 ? 'special-number' : ''}`}>
    {num}
  </span>
);

/**
 * A page component that displays the latest lottery results.
 * It fetches data using the useLotteryData hook and groups the results by lottery name.
 */
function LotteryResultsPage() {
  const { results, isLoading, error, getNumberColorClass } = useLotteryData();

  // Group results by lottery name for display.
  const groupedResults = results.reduce((acc, result) => {
    const key = result.lottery_name;
    if (!acc[key]) {
      acc[key] = [];
    }
    acc[key].push(result);
    return acc;
  }, {});

  if (isLoading) {
    return <div className="loading-container">正在加载最新的开奖记录...</div>;
  }

  if (error) {
    return <div className="error-container">错误: {error}</div>;
  }

  return (
    <div className="lottery-results-container">
      <h2>开奖记录</h2>
      {Object.keys(groupedResults).length === 0 ? (
        <p className="empty-state">目前还没有任何开奖记录。</p>
      ) : (
        Object.entries(groupedResults).map(([lotteryName, lotteryResults]) => (
          <div key={lotteryName} className="lottery-group">
            <h3>{lotteryName}</h3>
            <table className="lottery-results-table">
              <thead>
                <tr>
                  <th>开奖名称</th>
                  <th>期号</th>
                  <th>开奖号码</th>
                  <th>录入时间</th>
                </tr>
              </thead>
              <tbody>
                {lotteryResults.map((result) => (
                  <tr key={result.id}>
                    <td>{result.lottery_name}</td>
                    <td>{result.issue_number}</td>
                    <td className="number-cell">
                      {result.numbers.split(',').map((num, idx) => (
                        <LotteryNumber key={idx} num={num} idx={idx} getNumberColorClass={getNumberColorClass} />
                      ))}
                    </td>
                    <td>{new Date(result.parsed_at).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ))
      )}
    </div>
  );
}

export default LotteryResultsPage;