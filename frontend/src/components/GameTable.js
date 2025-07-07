import React from 'react';
import Card from './Card'; // Import the Card component

function GameTable({ cardsOnTable, bottomCards, gameState }) {
  // TODO: Implement game table display logic

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

      {/* Add other game table elements as needed */}
    </div>
  );
}

export default GameTable;