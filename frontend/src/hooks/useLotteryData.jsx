import { useState, useEffect, useMemo } from 'react';
import { getLotteryResults, getGameData } from '../api/client';

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
        // Use the new API client for fetching data
        const [lotteryResults, gameData] = await Promise.all([
          getLotteryResults(),
          getGameData()
        ]);

        setResults(lotteryResults);
        setColorMap(gameData.colorMap);

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
