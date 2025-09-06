import React from 'react';

function GameTable({ comparisonResults }) {
  return (
    <div className="game-table">
      <h2>牌局结果</h2>
      <div className="comparison-results">
        {Object.entries(comparisonResults).map(([key, score]) => (
          <div key={key} className="result-row">
            <span className="player-pair">{key.replace('_', ' vs ')}:</span>
            <span className={`score ${score > 0 ? 'score-positive' : 'score-negative'}`}>{score}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

export default GameTable;