import { useState, useEffect } from 'react';
import Auth from './components/Auth';
import './App.css';

function App() {
  const [lotteryData, setLotteryData] = useState(null);
  const [error, setError] = useState(null);
  const [user, setUser] = useState(null);

  // Check user session on initial load
  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await fetch('/api/check_session.php', {
          credentials: 'include',
        });
        const data = await response.json();
        if (data.loggedin) {
          setUser(data.user);
        }
      } catch (err) {
        // Not a critical error, user remains logged out
        console.error("Session check failed:", err);
      }
    };
    checkSession();
  }, []);

  // Fetch lottery data
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

  const handleLogin = (userData) => {
    setUser(userData);
  };

  const handleLogout = () => {
    setUser(null);
  };

  return (
    <div className="App">
      <Auth user={user} onLogin={handleLogin} onLogout={handleLogout} />
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