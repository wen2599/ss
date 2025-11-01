import React, { useState, useEffect } from 'react';
import axios from 'axios';

const API_URL = '/api_router.php?endpoint=lottery';

function LotteryResults() {
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchResults = async () => {
      try {
        const response = await axios.get(API_URL);
        
        if (response.data && response.data.success && Array.isArray(response.data.data)) {
          setResults(response.data.data);
        } else {
          setError('Failed to fetch lottery data or the format is incorrect.');
          setResults([]);
        }
      } catch (err) {
        setError('Could not load lottery data. Please check if the backend API is running.');
        console.error("API request failed:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchResults();
  }, []);

  if (loading) {
    return <p>Loading lottery results...</p>;
  }

  if (error) {
    return <p style={{ color: 'red' }}>{error}</p>;
  }

  return (
    <div className="lottery-results">
      <h2>最新开奖结果</h2>
      <table>
        <thead>
          <tr>
            <th>期号</th>
            <th>开奖日期</th>
            <th>开奖号码</th>
          </tr>
        </thead>
        <tbody>
          {results.length > 0 ? (
            results.map((result) => (
              <tr key={result.id}>
                <td>{result.issue_number}</td>
                <td>{result.draw_date}</td>
                <td>{result.numbers.replace('+', ' + ')}</td>
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan="3">No lottery data available.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

export default LotteryResults;
