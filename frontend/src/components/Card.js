import React from 'react';

function Card({ filename }) {
  // Placeholder for card value recognition logic
  const recognizeCardValue = (name) => {
    // This is where you'll add logic to parse the filename
    // and return the card value (e.g., "梅花10", "黑桃A")
    // For now, we'll just return the filename
    return name;
  };

  const cardValue = recognizeCardValue(filename);

  return (
    <div className="card">
      <img src={`/cards/${filename}`} alt={cardValue} />
      {/* Optional: display the recognized card value */}
      {/* <p>{cardValue}</p> */}
    </div>
  );
}

export default Card;