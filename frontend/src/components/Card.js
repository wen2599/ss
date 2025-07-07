import React from 'react';

function Card({ filename, onClick, isSelected }) {
  const recognizeCardValue = (name) => {
    const [valueStr, of, suitStr] = name.replace('.svg', '').split('_');

    // Handle Jokers
    if (valueStr === 'red') return '大王';
    if (valueStr === 'black') return '小王';

    let value = valueStr.toUpperCase();
    // Map face cards and Ace
    if (value === 'ACE') value = 'A';
    if (value === 'KING') value = 'K';
    if (value === 'QUEEN') value = 'Q';
    if (value === 'JACK') value = 'J';

    let suit = '';
    // Map suits
    if (suitStr === 'spades') suit = '♠';
    if (suitStr === 'hearts') suit = '♥';
    if (suitStr === 'diamonds') suit = '♦';
    if (suitStr === 'clubs') suit = '♣';

    return `${value}${suit}`;
  };

  const cardValue = recognizeCardValue(filename);

  // Optional: Add a visual representation of the card value on the card itself
  // This can be useful for debugging and testing
  const displayValue = filename === 'red_joker.svg' || filename === 'black_joker.svg'
    ? cardValue // For Jokers, display "大王" or "小王"
    : `${cardValue[0]}${cardValue[1]}`; // For numbered/face cards, display value and suit (e.g., "A♠", "10♣")

  return (
    <div
      className={`card ${isSelected ? 'selected-card' : ''}`} // Add 'selected-card' class if isSelected is true
      onClick={onClick} // Attach the onClick handler
    >
      <img src={`/cards/${filename}`} alt={cardValue} />
      {/* Display recognized card value on the card */}
      <div className="card-value-overlay">
        {displayValue}
      </div>
      {/* Optional: display the recognized card value */}
      {/* <p>{cardValue}</p> */}
    </div>
  );
}

export default Card;