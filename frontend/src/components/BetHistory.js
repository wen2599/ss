import React, { useState, useEffect } from 'react';
import { getUserBets } from '../api';
import { useError } from '../contexts/ErrorContext';

function BetHistory() {
  const [bets, setBets] = useState([]);
  const [loading, setLoading] = useState(true);
  const { setError } = useError();

  useEffect(() => {
    const fetchBets = async () => {
      try {
        const response = await getUserBets();
        if (response.success) {
          setBets(response.bets);
        } else {
          setError(response.message);
        }
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchBets();
  }, [setError]);

  if (loading) return <div>Loading bet history...</div>;

  return (
    <div>
      <h2>My Bet History</h2>
      {bets.length === 0 ? (
        <p>You have not placed any bets yet.</p>
      ) : (
        <ul>
          {bets.map((bet) => (
            <li key={bet.id}>
              Draw {bet.draw_number} - Bet: {JSON.parse(bet.bet_numbers).join(', ')} - Amount: {bet.bet_amount} - Status: {bet.status} - Winnings: {bet.winnings}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default BetHistory;
