import React, { useState, useEffect } from 'react';
import request from '../api';

function LotteryDraw() {
  const [draws, setDraws] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchDraws = async () => {
      try {
        const response = await request('get_draws');
        setDraws(response.draws);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchDraws();
  }, []);

  if (loading) return <div>Loading draws...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      <h2>Lottery Draws</h2>
      <ul>
        {draws.map((draw) => (
          <li key={draw.id}>
            <strong>Draw {draw.draw_number}</strong> ({draw.draw_date}) - Status: {draw.status}
            {draw.winning_numbers && (
              <div>
                Winning Numbers: {JSON.parse(draw.winning_numbers).join(', ')}
              </div>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}

export default LotteryDraw;
