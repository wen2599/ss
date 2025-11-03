import { useState, useEffect } from 'react';

function LotteryNumbers() {
  const [numbers, setNumbers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchNumbers = async () => {
      try {
        setLoading(true);
        // The worker proxies this request to the PHP backend
        const response = await fetch('/api/get_lottery_numbers.php');

        if (!response.ok) {
          // Try to get a more specific error message from the response body
          const errorText = await response.text();
          throw new Error(`HTTP error! Status: ${response.status}, Body: ${errorText}`);
        }

        const data = await response.json();

        if (data.success) {
          setNumbers(data.data);
        } else {
          throw new Error(data.message || 'Failed to fetch lottery data.');
        }

      } catch (err) {
        // Check for the common JSON parsing error
        if (err instanceof SyntaxError) {
             setError("Failed to parse server response. The API may be down or returning invalid data.");
        } else {
             setError(err.message);
        }
      } finally {
        setLoading(false);
      }
    };

    fetchNumbers();
  }, []); // Empty dependency array ensures this runs only once on mount

  return (
    <div className="lottery-container">
      <h2>开奖号码列表</h2>
      {loading && <p className="loading">正在加载中...</p>}
      {error && <p className="error">错误: {error}</p>}
      {!loading && !error && (
        <ul className="item-list">
          {numbers.length > 0 ? (
            numbers.map((item) => (
              <li key={item.id} className="lottery-item">
                <div className="lottery-header">
                  <strong>{item.lottery_type}</strong> - 第 {item.issue_number} 期
                </div>
                <div className="lottery-numbers">
                  {item.numbers}
                </div>
                <div className="lottery-footer">
                  {new Date(item.created_at).toLocaleString()}
                </div>
              </li>
            ))
          ) : (
            <p>还没有任何开奖号码。</p>
          )}
        </ul>
      )}
    </div>
  );
}

export default LotteryNumbers;
