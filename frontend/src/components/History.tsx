import React from 'react';
import { mockDraws } from '../data/mockData';
import Draw from './Draw';

const History: React.FC = () => {
  return (
    <div className="history-container">
      <h1>开奖历史</h1>
      {mockDraws.map(draw => (
        <Draw key={draw.period} draw={draw} />
      ))}
    </div>
  );
};

export default History;
