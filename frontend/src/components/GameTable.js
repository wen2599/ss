import React from 'react';
import Card from './Card'; // Import the Card component

function GameTable({ cardsOnTable, bottomCards }) {
  return (
    <div className="game-table-content">
      <div className="landlord-cards-display">
        {/* Display bottom cards only if they exist */}
        {bottomCards && bottomCards.length > 0 && (
          <div className="bottom-cards">
            {bottomCards.map((card, index) => (
              <Card key={index} cardName={card} />
            ))}
          </div>
        )}
      </div>

      <div className="discard-pile">
        {/* Display last played cards */}
        {cardsOnTable && cardsOnTable.length > 0 ? (
          cardsOnTable.map((card, index) => (
            <div key={index} data-testid="discard-card">
              <Card cardName={card} />
            </div>
          ))
        ) : (
          <p>等待玩家出牌...</p> /* Waiting for player to play */
        )}
      </div>
    </div>
  );
}

export default GameTable;