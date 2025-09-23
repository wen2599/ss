import React, { useState, useEffect } from 'react';
import LotteryBanner from '../components/LotteryBanner';

function LotteryResultsPage() {
  const [results, setResults] = useState([]);
  const [latestResult, setLatestResult] = useState(null);
  const [colorMap, setColorMap] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchData = async () => {
      setIsLoading(true);
      setError('');
      try {
        const [resultsResponse, gameDataResponse] = await Promise.all([
          fetch('/get_lottery_results'),
          fetch('/get_game_data')
        ]);

        console.log("Raw results response:", resultsResponse);
        console.log("Raw game data response:", gameDataResponse);

        const resultsData = await resultsResponse.json();
        const gameData = await gameDataResponse.json();

        console.log("Parsed lottery results data:", resultsData);
        console.log("Parsed game data:", gameData);

        if (resultsData.success && resultsData.results) {
          setResults(resultsData.results);
          if (resultsData.results.length > 0) {
            const latest = resultsData.results[0];
            setLatestResult(latest);
            console.log("Latest result set in state:", latest);
          } else {
            console.log("No lottery results found in data.");
          }
        } else {
          console.error("Fetching lottery results was not successful or data format is wrong.");
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
  }, []); // Fetch only once on component mount

  const numberColorCache = React.useMemo(() => {
    if (!colorMap) return {};
    const cache = {};
    // Pre-calculate the color for each number for quick lookups
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
      <LotteryBanner latestResult={latestResult} getNumberColorClass={getNumberColorClass} />
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
