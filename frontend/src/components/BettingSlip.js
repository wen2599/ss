import React, { useState } from 'react';
import request from '../api';
import { useError } from '../contexts/ErrorContext';

function BettingSlip() {
  const [numbers, setNumbers] = useState('');
  const [amount, setAmount] = useState('');
  const { setError } = useError();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);

    try {
      const betNumbers = numbers.split(',').map(n => parseInt(n.trim(), 10));
      const betAmount = parseInt(amount, 10);

      // TODO: Implement draw selection. Using a hardcoded draw_id for now.
      const drawId = 1;

      await request('place_bet', 'POST', {
        draw_id: drawId,
        bet_type: 'single', // TODO: Implement bet type selection
        bet_numbers: betNumbers,
        bet_amount: betAmount,
      });

      alert('Bet placed successfully!');
      setNumbers('');
      setAmount('');
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div>
      <h2>Place a Bet</h2>
      <form onSubmit={handleSubmit}>
        <div>
          <label>Numbers (comma-separated):</label>
          <input
            type="text"
            value={numbers}
            onChange={(e) => setNumbers(e.target.value)}
            required
          />
        </div>
        <div>
          <label>Amount:</label>
          <input
            type="number"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            required
          />
        </div>
        <button type="submit">Place Bet</button>
      </form>
    </div>
  );
}

export default BettingSlip;
