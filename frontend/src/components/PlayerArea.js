import React, { useState } from 'react';
import Card from './Card';
// import { playCards, passTurn, bid } from '../api'; // We'll need these later

function PlayerArea({ player, isCurrentPlayer, gameState }) {
  const [selectedCards, setSelectedCards] = useState([]);

  if (!player) {
    return <div className="player-area" />;
  }

  const handleCardClick = (cardName) => {
    // Only the current player can interact with their own cards
    if (!isCurrentPlayer) return;

    setSelectedCards(prev =>
      prev.includes(cardName)
        ? prev.filter(c => c !== cardName)
        : [...prev, cardName]
    );
  };

  const handlePlay = () => {
    console.log("Play:", selectedCards);
    // TODO: Call API: playCards(gameId, player.id, selectedCards);
    setSelectedCards([]);
  };

  const handleBid = (amount) => {
    console.log("Bid:", amount);
    // TODO: Call API: bid(gameId, player.id, amount);
  };

  const handlePass = () => {
    console.log("Pass");
    // TODO: Call API: passTurn(gameId, player.id);
  };

  // For opponents, we render placeholders based on their card count.
  // For the current player, we render their actual hand.
  const handToRender = isCurrentPlayer
    ? player.hand
    : Array(player.hand_count || 0).fill('face-down');

  return (
    <div className={`player-area ${isCurrentPlayer ? 'current-player-active' : ''}`}>
      <div className="player-info">
        <span className="player-name">{player.name} {player.isLandlord ? '地主' : ''}</span>
        <span className="card-count">{player.hand_count} 张</span>
      </div>

      <div className="player-hand">
        {handToRender.map((cardName, index) => (
          <Card
            key={index}
            cardName={cardName}
            onClick={() => handleCardClick(cardName)}
            isSelected={selectedCards.includes(cardName)}
            isFaceDown={!isCurrentPlayer}
          />
        ))}
      </div>

      {isCurrentPlayer && (
        <div className="action-buttons">
          {gameState === 'bidding' && (
            <>
              <button onClick={() => handleBid(1)}>1分</button>
              <button onClick={() => handleBid(2)}>2分</button>
              <button onClick={() => handleBid(3)}>3分</button>
              <button onClick={handlePass}>不叫</button>
            </>
          )}
          {gameState === 'playing' && (
             <>
              <button onClick={handlePlay} disabled={selectedCards.length === 0}>出牌</button>
              <button onClick={handlePass}>不要</button>
            </>
          )}
        </div>
      )}
    </div>
  );
}

export default PlayerArea;
