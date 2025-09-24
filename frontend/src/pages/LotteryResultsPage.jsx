import React from 'react';
import { useLotteryData } from '../hooks/useLotteryData';

function LotteryResultsPage() {
  const { results, isLoading, error, getNumberColorClass } = useLotteryData({ apiPrefix: '/api' });

  const groupedResults = results.reduce((acc, result) => {
    const key = result.lottery_name;
    if (!acc[key]) {
      acc[key] = [];
    }
    acc[key].push(result);
    return acc;
  }, {});

  if (isLoading) {
    return <div>正在加载开奖记录...</div>;
  }

  if (error) {
    return <div className="error">{error}</div>;
  }

  return (
    <div className="bills-container">
      <h2>开奖记录</h2>
      {Object.keys(groupedResults).length === 0 ? (
        <p>还没有任何开奖记录。</p>
      ) : (
        Object.entries(groupedResults).map(([lotteryName, lotteryResults]) => (
          <div key={lotteryName} className="lottery-group">
            <h3>{lotteryName}</h3>
            <table className="bills-table">
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
                    <span key={num} className={`${getNumberColorClass(num)} ${idx === 6 ? 'special-number' : ''}`}>
                      {num}
                    </span>
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
