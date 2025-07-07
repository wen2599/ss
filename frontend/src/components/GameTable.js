import React from 'react';
import Card from './Card'; // Import the Card component

function GameTable({ cardsOnTable }) {
function GameTable({ cardsOnTable, bottomCards, gameState }) {
  // TODO: Implement game table display logic

  // Determine if bottom cards should be visible (e.g., after calling landlord phase)
  const areBottomCardsVisible = gameState && (gameState === 'playing' || gameState === 'finished'); // Adjust condition based on your backend state

  return (
    <div className="game-table">
      <h2>游戏桌面</h2>
      <div className="discarded-cards">
        {/* TODO: Display last played cards more prominently */}

        <h3>出牌区域</h3>
        {cardsOnTable.map((cardFilename, index) => (
          <Card key={index} filename={cardFilename} />
        ))}
        <p>出牌区域</p>
      </div>
      <div className="deck">
        {/* TODO: Display deck placeholder or remaining cards count */}
        <p>牌堆</p>
      </div>

      {/* Display bottom cards when visible */}
      {areBottomCardsVisible && (
        <div className="bottom-cards">
          <h3>底牌</h3>
          {bottomCards.map((cardFilename, index) => (
            <Card key={index} filename={cardFilename} />
          ))}
        </div>
      )}

      {/* Add other game table elements as needed */}
    </div>
  );
}

export default GameTable;