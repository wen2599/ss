import React from 'react';
import { DrawResult } from '../data/mockData';

interface DrawProps {
  draw: DrawResult;
}

const Draw: React.FC<DrawProps> = ({ draw }) => {
  return (
    <div className="draw-item">
      <h3>期数: {draw.period}</h3>
      <p>开奖日期: {draw.date}</p>
      <div className="draw-numbers">
        <span>正码: {draw.numbers.join(', ')}</span>
        <span className="special-number">  特码: {draw.specialNumber}</span>
      </div>
    </div>
  );
};

export default Draw;
