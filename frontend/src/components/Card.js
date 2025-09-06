import React from 'react';

// Maps the backend's concise card format to the frontend's SVG filenames and display values.
const cardMap = {
  S: { suit: 'spades', symbol: '♠' },
  H: { suit: 'hearts', symbol: '♥' },
  D: { suit: 'diamonds', symbol: '♦' },
  C: { suit: 'clubs', symbol: '♣' },
  A: { value: 'ace', display: 'A' },
  K: { value: 'king', display: 'K' },
  Q: { value: 'queen', display: 'Q' },
  J: { value: 'jack', display: 'J' },
  T: { value: '10', display: '10' },
  '9': { value: '9', display: '9' },
  '8': { value: '8', display: '8' },
  '7': { value: '7', display: '7' },
  '6': { value: '6', display: '6' },
  '5': { value: '5', display: '5' },
  '4': { value: '4', 'display': '4' },
  '3': { value: '3', display: '3' },
  '2': { value: '2', display: '2' },
};

function Card({ filename, onClick, isSelected }) {
  // The 'filename' prop now contains the concise format, e.g., "SA"
  const cardString = filename;

  if (!cardString || cardString.length < 2) {
    // Hide or show a placeholder for invalid card strings
    // Old card formats like "black_joker.svg" will also fail this and not be rendered.
    return null;
  }

  const suitChar = cardString.charAt(0).toUpperCase();
  const rankChar = cardString.charAt(1).toUpperCase();

  const suitInfo = cardMap[suitChar];
  const rankInfo = cardMap[rankChar] || cardMap[cardString.charAt(1)]; // Handle 'T' for 10

  if (!suitInfo || !rankInfo) {
    console.error("Invalid card format passed to Card component:", cardString);
    return <div className="card error-card">?</div>;
  }

  const svgFilename = `${rankInfo.value}_of_${suitInfo.suit}.svg`;
  const displayValue = `${rankInfo.display}${suitInfo.symbol}`;

  return (
    <div
      className={`card ${isSelected ? 'selected-card' : ''}`}
      onClick={onClick}
    >
      <img src={`/cards/${svgFilename}`} alt={displayValue} />
      <div className="card-value-overlay">
        {displayValue}
      </div>
    </div>
  );
}

export default Card;
