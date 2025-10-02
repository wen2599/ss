import { useState, useEffect, useMemo } from 'react';

export function useLotteryData() {
  const [results, setResults] = useState([]);
  const [colorMap, setColorMap] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchInitialData = async () => {
      // This function runs once to get all initial data, including the static color map.
      setIsLoading(true);
      setError('');
      try {
        const [resultsResponse, gameDataResponse] = await Promise.all([
          fetch('/get_lottery_results'),
          fetch('/get_game_data')
        ]);

        // Handle HTTP errors for both requests
        if (!resultsResponse.ok) {
          throw new Error(`获取开奖结果失败: HTTP ${resultsResponse.status}`);
        }
        if (!gameDataResponse.ok) {
          throw new Error(`获取游戏数据失败: HTTP ${gameDataResponse.status}`);
        }

        const resultsData = await resultsResponse.json();
        const gameData = await gameDataResponse.json();

        if (resultsData.success) {
          setResults(resultsData.results);
        } else {
          throw new Error(resultsData.error || '获取开奖结果失败。');
        }

        if (gameData.success) {
          setColorMap(gameData.colorMap);
        } else {
          throw new Error(gameData.error || '获取游戏数据失败。');
        }

      } catch (err) {
        // Check if the error is from a JSON parsing failure
        if (err instanceof SyntaxError) {
            setError('无法解析服务器响应，后端可能出错。');
        } else {
            setError(err.message || '获取数据时发生错误。');
        }
      } finally {
        setIsLoading(false);
      }
    };

    const pollResults = async () => {
        // This function runs repeatedly to only update the lottery results.
        // It does not touch the loading or error states to avoid UI flashes.
        try {
            const resultsResponse = await fetch('/get_lottery_results');

            // Silently fail on HTTP error during polling to avoid UI disruption
            if (!resultsResponse.ok) {
                console.error(`Polling HTTP error: ${resultsResponse.status}`);
                return;
            }

            const resultsData = await resultsResponse.json();
            if (resultsData.success) {
                // Optimization: Only update state if the results have actually changed.
                // This avoids unnecessary re-renders every 10 seconds.
                setResults(currentResults => {
                    const newResults = resultsData.results;
                    if (JSON.stringify(currentResults) === JSON.stringify(newResults)) {
                        return currentResults; // Return the old state to prevent re-render
                    }
                    return newResults; // Return the new state
                });
            } else {
                console.error("Polling error:", resultsData.error || 'Unknown polling error');
            }
        } catch(err) {
            // Also handle JSON parsing errors silently in the background
            if (err instanceof SyntaxError) {
                console.error("Polling JSON parsing error.");
            } else {
                console.error("Polling network error:", err.message);
            }
        }
    };

    fetchInitialData();
    const intervalId = setInterval(pollResults, 10000); // Poll every 10 seconds

    return () => clearInterval(intervalId); // Cleanup on unmount
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
