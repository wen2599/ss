import React, { useState, useEffect } from 'react';
import LotteryBanner from './components/LotteryBanner';
import './App.css';

function App() {
  const [latestResults, setLatestResults] = useState([]);
  const [colorMap, setColorMap] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // This is a mock of the getNumberColorClass function,
  // as the real one is in LotteryResultsPage.
  // For a real app, this logic should be moved to a shared utility file.
  const numberColorCache = React.useMemo(() => {
    if (!colorMap) return {};
    const cache = {};
    for (const color of Object.keys(colorMap)) {
      const colorName = color === '红波' ? 'red' : color === '蓝波' ? 'blue' : 'green';
      for (const number of [...colorMap[color].single, ...colorMap[color].double]) {
        cache[number] = colorName;
      }
    }
    return cache;
  }, [colorMap]);

  const getNumberColorClass = (number) => {
    const color = numberColorCache[number];
    return color ? `number-ball number-ball-${color}` : 'number-ball number-ball-default';
  };


  useEffect(() => {
    const fetchData = async () => {
      setIsLoading(true);
      setError('');
      try {
        const [resultsResponse, gameDataResponse] = await Promise.all([
          fetch('/get_lottery_results'),
          fetch('/get_game_data')
        ]);

        const resultsData = await resultsResponse.json();
        const gameData = await gameDataResponse.json();

        if (resultsData.success && resultsData.results) {
          setLatestResults(resultsData.results.slice(0, 3));
        } else {
          throw new Error(resultsData.error || 'Failed to fetch lottery results.');
        }

        if (gameData.success) {
          setColorMap(gameData.colorMap);
        } else {
          throw new Error(gameData.error || 'Failed to fetch game data.');
        }

      } catch (err) {
        setError(err.message || 'An error occurred while fetching data.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchData();
  }, []);

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
