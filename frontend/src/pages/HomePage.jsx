import React, { useState, useEffect } from 'react';

function HomePage() {
  const [lotteryData, setLotteryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState('');

  useEffect(() => {
    const fetchLotteryNumber = async () => {
      setLoading(true);
      setError(null);
      setMessage('');

      try {
        const response = await fetch('/api/?action=get_latest_lottery_result');

        if (!response.ok) {
          throw new Error(`Server responded with status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          setLotteryData(data.data);
        } else {
          setLotteryData(null);
          setMessage(data.message || 'No data available.');
        }

      } catch (e) {
        console.error("Fetch error:", e);
        setError(e.message);
      } finally {
        setLoading(false);
      }
    };

    fetchLotteryNumber();
    const intervalId = setInterval(fetchLotteryNumber, 60000);
    return () => clearInterval(intervalId);
  }, []);

  return (
    <div className="container">
      <header>
        <h1>Latest Lottery Result</h1>
      </header>
      <main>
        <div className="card">
          {loading && <p className="status">Loading...</p>}
          {error && <p className="status error">Failed to load: {error}</p>}
          {!loading && !error && message && <p className="status">{message}</p>}
          {!loading && !error && lotteryData && (
            <div className="result">
              <span className="number-label">{lotteryData.lottery_type} ({lotteryData.issue_number}):</span>
              <span className="number">{lotteryData.numbers}</span>
              <span className="time">
                Draw Time: {lotteryData.draw_time ? new Date(lotteryData.draw_time).toLocaleString() : 'N/A'}
              </span>
            </div>
          )}
        </div>
      </main>
      <footer>
        <p>Data refreshes automatically every minute.</p>
      </footer>
    </div>
  );
}

export default HomePage;