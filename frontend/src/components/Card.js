import React from 'react';
import { mapCardNameToFilename } from '../utils/mapCardName';

// The new App.css handles the .card, .selected, and .face-down styles
function Card({ cardName, onClick, isSelected, isFaceDown = false }) {
  // If no cardName, we can render a placeholder or nothing
  if (!cardName) {
    return <div className="card-placeholder" style={{width: '70px', height: '100px'}} />;
  }

  const filename = mapCardNameToFilename(cardName);
  const cardPath = `/cards/${filename}`;
  const cardClasses = `card ${isSelected ? 'selected' : ''} ${isFaceDown ? 'face-down' : ''}`;

  return (
    <div className={cardClasses} onClick={onClick}>
      <img src={cardPath} alt={cardName} />
    </div>
  );
}

export default Card;