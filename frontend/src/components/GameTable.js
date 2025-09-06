import React from 'react';
import Card from './Card';

// A new component to display one player's result
function PlayerResult({ playerResult }) {
  // This component assumes playerResult contains the arranged hands and scores.
  // This data will need to be passed down from App.js once the game state includes it.
  return (
    <div className="player-result">
      <h4>{playerResult.name} (得分: {playerResult.roundPoints || 0})</h4>
      <div className="result-hands">
        <div className="hand-row">
          <strong>前墩:</strong>
          <div className="player-hand-small">
            {(playerResult.frontHand || []).map(card => <Card key={card} filename={card} />)}
          </div>
        </div>
        <div className="hand-row">
          <strong>中墩:</strong>
          <div className="player-hand-small">
            {(playerResult.middleHand || []).map(card => <Card key={card} filename={card} />)}
          </div>
        </div>
        <div className="hand-row">
          <strong>后墩:</strong>
          <div className="player-hand-small">
            {(playerResult.backHand || []).map(card => <Card key={card} filename={card} />)}
          </div>
        </div>
      </div>
    </div>
  );
}


function GameTable({ gameState, players }) {
  // If the game is in a state where results should be shown.
  const showResults = gameState === 'finished' || gameState === 'comparing';

  return (
    <div className="game-table">
      {showResults && players ? (
        <div className="results-container">
          <h2>本局结果</h2>
          {players.map(player => (
            <PlayerResult key={player.id} playerResult={player} />
          ))}
        </div>
      ) : (
        <div className="arranging-table">
          <h2>理牌阶段</h2>
          <p>请在您的操作区域将13张牌设置到前、中、后三墩。</p>
          <p>所有玩家确认后，将开始比牌。</p>
        </div>
      )}
    </div>
  );
}

export default GameTable;