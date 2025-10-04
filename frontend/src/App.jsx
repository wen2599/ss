import { useState, useEffect } from 'react';
import './App.css';

function App() {
  const [lotteryData, setLotteryData] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetch('/api/get_numbers.php')
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        if (data.error) {
          throw new Error(data.error);
        }
        setLotteryData(data);
      })
      .catch(error => {
        setError(error.message);
      });
  }, []);

  return (
    <div className="App">
      <header className="App-header">
        <h1>六合彩开奖结果</h1>
      </header>
      <main>
        {error && <p className="error">Error: {error}</p>}
        {lotteryData ? (
          <div className="lottery-numbers">
            <h2>期号: {lotteryData.issue}</h2>
            <div className="numbers">
              {lotteryData.numbers.map((number, index) => (
                <span key={index} className="number-ball">{number}</span>
              ))}
            </div>
          </div>
        ) : (
          <p>Loading...</p>
        )}
      </main>
    </div>
  );
}

export default App;