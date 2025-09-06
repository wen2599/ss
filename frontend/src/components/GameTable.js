import React from 'react';
import Card from './Card';

const PlayerShowdownHand = ({ handData }) => {
  if (!handData) return null;

  return (
    <div className={`showdown-player-hand ${!handData.isValid ? 'scooped' : ''}`}>
      <h4>Player {handData.playerId.slice(-4)}</h4>
      {!handData.isValid ? (
        <p className="scooped-message">SCOOPED!</p>
      ) : (
        <>
          <div className="hand-row">{handData.back.map(c => <Card key={c} filename={c} />)}</div>
          <div className="hand-row">{handData.middle.map(c => <Card key={c} filename={c} />)}</div>
          <div className="hand-row">{handData.front.map(c => <Card key={c} filename={c} />)}</div>
        </>
      )}
      <p>Round Score: {handData.roundScore}</p>
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