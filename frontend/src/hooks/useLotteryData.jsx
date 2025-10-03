import { useState, useEffect, useMemo } from 'react';
import { getLotteryResults, getGameData } from '../services/api';

/**
 * @file A custom hook to fetch and manage lottery data.
 * It handles initial data loading, background polling for updates, and provides
 * derived data like the color map for lottery numbers.
 */

/**
 * Fetches and manages lottery results and game data.
 *
 * @returns {{
 *   results: Array<object>,
 *   isLoading: boolean,
 *   error: string,
 *   getNumberColorClass: (number: string) => string
 * }} An object containing the lottery results, loading state, error message,
 *    and a helper function to get the CSS class for a number's color.
 */
export function useLotteryData() {
  const [results, setResults] = useState([]);
  const [colorMap, setColorMap] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    /**
     * Fetches the initial data (results and color map) on component mount.
     */
    const fetchInitialData = async () => {
      setIsLoading(true);
      setError('');
      try {
        // Use the centralized apiService for fetching data.
        // It handles JSON parsing and error checking internally.
        const [resultsData, gameData] = await Promise.all([
          getLotteryResults(),
          getGameData()
        ]);

        setResults(resultsData.results);
        setColorMap(gameData.colorMap);

      } catch (err) {
        setError(err.message || '获取数据时发生未知错误。');
      } finally {
        setIsLoading(false);
      }
    };

    /**
     * Polls for new lottery results periodically in the background.
     * Errors are logged to the console to avoid disrupting the user experience.
     */
    const pollResults = async () => {
        try {
            const newResultsData = await getLotteryResults();

            // Optimization: Only update state if the results have actually changed.
            setResults(currentResults => {
                const newResults = newResultsData.results;
                if (JSON.stringify(currentResults) === JSON.stringify(newResults)) {
                    return currentResults; // Prevent re-render if data is the same
                }
                return newResults;
            });
        } catch(err) {
            // Silently log polling errors to the console.
            console.error("Polling for lottery results failed:", err.message);
        }
    };

    fetchInitialData();
    const intervalId = setInterval(pollResults, 10000); // Poll every 10 seconds

    // Cleanup interval on component unmount.
    return () => clearInterval(intervalId);
  }, []);

  /**
   * A memoized cache for mapping lottery numbers to their respective color class names.
   * This avoids recalculating on every render.
   */
  const numberColorCache = useMemo(() => {
    if (!colorMap) return {};
    const cache = {};
    for (const color of Object.keys(colorMap)) {
      // Map color names from Chinese to English CSS class names.
      const colorName = color === '红波' ? 'red' : color === '蓝波' ? 'blue' : 'green';
      for (const number of [...colorMap[color].single, ...colorMap[color].double]) {
        cache[number] = colorName;
      }
    }
    return cache;
  }, [colorMap]);

  /**
   * Returns the appropriate CSS class for a given lottery number based on its color.
   * @param {string} number The lottery number.
   * @returns {string} The CSS class string.
   */
  const getNumberColorClass = (number) => {
    const color = numberColorCache[number];
    return color ? `number-ball number-ball-${color}` : 'number-ball number-ball-default';
  };

  return { results, isLoading, error, getNumberColorClass };
}