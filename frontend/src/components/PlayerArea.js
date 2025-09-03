import React, { useState } from 'react';
import Card from './Card';
import { playCards } from '../api';

function PlayerArea({ player, isCurrentPlayer, gameId, roomId, onPlay }) {
  const [selectedCards, setSelectedCards] = useState([]);

  const handleCardClick = (cardFilename) => {
    if (!isCurrentPlayer) return;

    if (selectedCards.includes(cardFilename)) {
      setSelectedCards(selectedCards.filter(card => card !== cardFilename));
    } else {
      setSelectedCards([...selectedCards, cardFilename]);
    }
  };
  
  const handleCancelSelection = () => {
    setSelectedCards([]);
  };

  const handlePlayCards = async () => {
    if (selectedCards.length === 0) return;
    if (!gameId) {
      alert("Game has not started yet.");
      return;
    }

    try {
      const response = await playCards(gameId, player.id, selectedCards);
      if (response.success) {
        setSelectedCards([]);
        onPlay(roomId, player.id); // Refresh game state
      } else {
        alert(`Invalid move: ${response.message}`);
      }
    } catch (error) {
      console.error('Failed to play cards:', error);
      alert('An error occurred while playing cards.');
    }
  };

  return (
    <div className={`player-area ${player.isLandlord ? 'landlord' : ''} ${player.isCurrentPlayer ? 'current-player-area' : ''}`}>
      <h3>{player.name} {player.isLandlord && '(地主)'}</h3>
      <p>Score: {player.score}</p>

      <div className="player-hand">
        {player.hand && player.hand.map((cardFilename, index) => (
          <Card
            key={index}
            filename={cardFilename}
            onClick={() => handleCardClick(cardFilename)}
            isSelected={selectedCards.includes(cardFilename)}
          />
        ))}
      </div>

      {isCurrentPlayer && (
        <div className="action-buttons">
          <button disabled={true}>叫地主</button>
          <button disabled={true}>不叫</button>
          <button onClick={handlePlayCards} disabled={selectedCards.length === 0}>出牌</button>
          <button onClick={() => console.log("Pass")}>不要</button>
          <button onClick={handleCancelSelection}>取消选择</button>
        </div>
      )}
    </div>
  );
}

export default PlayerArea;
