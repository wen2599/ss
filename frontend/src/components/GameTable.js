import React from 'react';
import Card from './Card';

const PlayerShowdownHand = ({ handData }) => {
  if (!handData) return null;

  const { scores, isValid, playerId, back, middle, front } = handData;
  const royalty_details = scores?.royalty_details;

  return (
    <div className={`showdown-player-hand ${!isValid ? 'scooped' : ''}`}>
      <h4>Player {playerId.slice(-4)}</h4>
      {!isValid ? (
        <p className="scooped-message">SCOOPED! (-6)</p>
      ) : (
        <>
          <div className="hand-row">
            <span>Back:</span>
            {back.map(c => <Card key={c} filename={c} />)}
            {royalty_details?.back > 0 && <span className="royalty-badge">+{royalty_details.back}</span>}
          </div>
          <div className="hand-row">
            <span>Middle:</span>
            {middle.map(c => <Card key={c} filename={c} />)}
            {royalty_details?.middle > 0 && <span className="royalty-badge">+{royalty_details.middle}</span>}
          </div>
          <div className="hand-row">
            <span>Front:</span>
            {front.map(c => <Card key={c} filename={c} />)}
            {royalty_details?.front > 0 && <span className="royalty-badge">+{royalty_details.front}</span>}
          </div>
        </>
      )}
      {scores ? (
        <div className="score-details">
            <p>Comparison: {scores.comparison_score > 0 ? `+${scores.comparison_score}` : scores.comparison_score}</p>
            <p>Royalties Payout: {scores.total_royalty_payout > 0 ? `+${scores.total_royalty_payout}` : scores.total_royalty_payout}</p>
            <p><strong>Round Total: {scores.final_score > 0 ? `+${scores.final_score}` : scores.final_score}</strong></p>
        </div>
      ) : (
          <p>Round Score: {handData.roundScore}</p>
      )}
    </div>
  );
};

function GameTable({ game }) {
  if (!game) {
    return (
      <div className="game-table">
        <p>Waiting for game to start...</p>
      </div>
    );
  }

  const renderContent = () => {
    switch (game.state) {
      case 'setting_hands':
        const submittedCount = game.hands.filter(h => h.isSubmitted).length;
        const totalPlayers = game.hands.length;
        return (
          <div>
            <h2>Setting Hands</h2>
            <p>Please arrange your 13 cards into a Front, Middle, and Back hand.</p>
            <p>Make sure your back hand is the strongest, and your front hand is the weakest!</p>
            <h3>{submittedCount} / {totalPlayers} players ready</h3>
          </div>
        );

      case 'showdown':
      case 'finished':
        return (
          <div>
            <h2>Showdown</h2>
            <div className="showdown-area">
              {game.hands.map(handData => (
                <PlayerShowdownHand key={handData.playerId} handData={handData} />
              ))}
            </div>
          </div>
        );

      default:
        return <p>Game state: {game.state}</p>;
    }
  };

  return (
    <div className="game-table">
      {renderContent()}
    </div>
  );
}

export default GameTable;