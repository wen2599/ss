import { useState, useEffect, useMemo } from 'react';

export function useLotteryData() {
  const [results, setResults] = useState([]);
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

        const resultsData = await resultsResponse.json();
        const gameData = await gameDataResponse.json();

        if (resultsData.success) {
          setResults(resultsData.results);
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

  const numberColorCache = useMemo(() => {
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

  return { results, isLoading, error, getNumberColorClass };
}
