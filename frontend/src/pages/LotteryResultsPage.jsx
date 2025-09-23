import React, { useState, useEffect } from 'react';

function LotteryResultsPage() {
  const [results, setResults] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchResults = async () => {
      setIsLoading(true);
      setError('');
      try {
        const response = await fetch('/api/get_lottery_results');
        const data = await response.json();

        if (data.success) {
          setResults(data.results);
        } else {
          setError(data.error || 'Failed to fetch lottery results.');
        }
      } catch (err) {
        setError('An error occurred while fetching results. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchResults();
  }, []); // Fetch only once on component mount

  if (isLoading) {
    return <div>正在加载开奖记录...</div>;
  }

  if (error) {
    return <div className="error">{error}</div>;
  }

  return (
    <div className="bills-container">
      <h2>开奖记录</h2>
      {results.length === 0 ? (
        <p>还没有任何开奖记录。</p>
      ) : (
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
            {results.map((result, index) => (
              <tr key={index}>
                <td>{result.lottery_name}</td>
                <td>{result.issue_number}</td>
                <td>{result.numbers}</td>
                <td>{new Date(result.parsed_at).toLocaleString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

export default LotteryResultsPage;
