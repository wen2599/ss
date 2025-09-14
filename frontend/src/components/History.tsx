import React, { useEffect, useState } from 'react';
import Draw from './Draw';

interface DrawResult {
  period: number;
  date: string;
  numbers: number[];
  specialNumber: number;
}

const History: React.FC = () => {
  const [draws, setDraws] = useState<DrawResult[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchDraws = async () => {
      try {
        const response = await fetch('https://wenge.cloudns.ch/api/api.php');
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        const data = await response.json();
        setDraws(data);
      } catch (error) {
        setError(error.message);
      }
    };

    fetchDraws();
  }, []);

  if (error) {
    return <div>Error: {error}</div>;
  }

  return (
    <div className="history-container">
      <h1>开奖历史</h1>
      {draws.map(draw => (
        <Draw key={draw.period} draw={draw} />
      ))}
    </div>
  );
};

export default History;
