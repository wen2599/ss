import React from 'react';
import Card from './Card'; // Assuming Card component is in the same directory

function PlayerArea({ player, hand }) {
  return (
    <div className="player-area">
      <h3>{player.name}</h3>
      <div className="hand">
        {/* Placeholder for displaying hand */}
        {hand.map((cardFilename, index) => (
          <Card key={index} filename={cardFilename} />
        ))}
      </div>
      {/* Placeholder for other player info like score */}
      <p>Score: {player.score}</p>
      {/* Placeholder for actions like '叫地主', '出牌' */}
      <div className="actions">
        {/* Buttons or other interactive elements */}
        <button>叫地主</button>
        <button>出牌</button>
      </div>
    </div>
  );
}

export default PlayerArea;