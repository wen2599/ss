import React, { useEffect, useState } from 'react';
import { getLotteryResults } from '../api';
import './LotteryPage.css';

const LotteryPage = () => {
  const [lotteryResults, setLotteryResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchLotteryResults = async () => {
      try {
        setLoading(true);
        const data = await getLotteryResults();
        if (data.success) {
          setLotteryResults(data.lotteryResults);
        } else {
          setError(data.error || 'Failed to fetch lottery results.');
        }
      } catch (err) {
        setError(err.message || 'An error occurred while fetching lottery results.');
      } finally {
        setLoading(false);
      }
    };

    fetchLotteryResults();
  }, []);

  if (loading) return <div>Loading lottery results...</div>;
  if (error) return <div className="alert error">Error: {error}</div>;

  return (
    <div className="lottery-page">
      <h1>Latest Lottery Results</h1>
      {lotteryResults.length === 0 ? (
        <p>No lottery results found yet.</p>
      ) : (
        <table className="lottery-table">
          <thead>
            <tr>
              <th>Draw Date</th>
              <th>Winning Numbers</th>
            </tr>
          </thead>
          <tbody>
            {lotteryResults.map((result) => (
              <tr key={result.id}>
                <td>{new Date(result.draw_date).toLocaleDateString()}</td>
                <td>{result.winning_numbers}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default LotteryPage;
