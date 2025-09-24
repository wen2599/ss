import React from 'react';
import { useLotteryData } from './hooks/useLotteryData';
import LotteryBanner from './components/LotteryBanner';
import './App.css';

function App() {
  const { results, isLoading, error, getNumberColorClass } = useLotteryData();
  const latestResults = results.slice(0, 3);

  if (isLoading) {
    return <div>正在加载最新开奖...</div>;
  }

  if (error) {
    return <div className="error">{error}</div>;
  }

  return (
    <div>
      {latestResults.length > 0 ? (
        latestResults.map(result => (
          <LotteryBanner
            key={result.id}
            latestResult={result}
            getNumberColorClass={getNumberColorClass}
          />
        ))
      ) : (
        <p>暂无开奖记录。</p>
      )}
    </div>
  );
}

export default App;
