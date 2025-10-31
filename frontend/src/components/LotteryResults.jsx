import React, { useState, useEffect } from 'react';
import axios from 'axios';

// The API URL is constructed using an environment variable for flexibility.
const API_URL = `${import.meta.env.VITE_API_BASE_URL}/get_results.php`;

function LotteryResults() {
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchResults = async () => {
      try {
        const response = await axios.get(API_URL);
        
        // Check if the response is successful and contains the data array.
        if (response.data && response.data.success && Array.isArray(response.data.data)) {
          setResults(response.data.data);
        } else {
          // Handle cases where the API returns a success=false or unexpected structure.
          setError('获取开奖数据失败或格式不正确。');
          setResults([]);
        }
      } catch (err) {
        setError('无法加载开奖数据，请检查后端 API 是否正常。');
        console.error("API request failed:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchResults();
  }, []); // The empty dependency array ensures this effect runs only once on mount.

  // --- Render Logic ---

  if (loading) {
    return <p>加载开奖结果中...</p>;
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
              <td colSpan="3">暂无开奖数据。</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

export default LotteryResults;