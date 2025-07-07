import React, { useState } from 'react'; // Import useState
import Card from './Card'; // Assuming Card component is in the same directory

function PlayerArea({ player, isCurrentPlayer }) {
  // State to keep track of selected cards (for the current player)
  const [selectedCards, setSelectedCards] = useState([]);
  // State to store the result of the play validation
  const [currentPlay, setCurrentPlay] = useState({ valid: false, type: null, value: null, length: 0 });

  // Helper function to get card value from filename (you might have this in utils or Card component)
  const getCardComparableValue = (filename) => {
    const nameParts = filename.split('_');
    const value = nameParts[0];
    const suit = nameParts[2] ? nameParts[2].split('.')[0] : '';

    const valueMap = {
      '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9, '10': 10,
      'jack': 11, 'queen': 12, 'king': 13, 'ace': 14, '2': 15
    };

    let numericalValue = valueMap[value] || 0; // Return numeric value, 0 for unknown

    if (filename === 'black_joker.svg') numericalValue = 16;
    if (filename === 'red_joker.svg') numericalValue = 17;

    const suitMap = {
      'diamonds': 1, 'clubs': 2, 'hearts': 3, 'spades': 4
    };
    let suitValue = suitMap[suit] || 0; // Return numeric suit value, 0 for jokers/unknown

    return { value: numericalValue, suit: suitValue, filename: filename };
  };

  // Function to compare two cards for sorting
  const compareCards = (cardA, cardB) => {
      if (cardA.value !== cardB.value) return cardA.value - cardB.value;
      return cardA.suit - cardB.suit;
  };

  // Function to validate the selected cards as a valid play
  const isValidPlay = (cards) => {
    const numCards = cards.length;
    if (numCards === 0) {
      return { valid: false, type: null };
    }

    // Convert filenames to comparable card objects and sort
    const comparableCards = cards.map(getCardComparableValue).sort(compareCards);
    const cardValues = comparableCards.map(card => card.value);

    // Rocket (Huojian) - two jokers (values 16 and 17)
    if (numCards === 2 && cardValues.includes(16) && cardValues.includes(17)) {
         return { valid: true, type: 'rocket', value: 18, length: 2 }; // Rocket has highest value
    }


    if (numCards === 1) {
      return { valid: true, type: 'single', value: cardValues[0], length: 1 };
    }
    
    if (numCards === 2 && cardValues[0] === cardValues[1]) {
      // Check for rocket (火箭) - handled separately in canBeatLastPlay
      return { valid: true, type: 'pair', value: cardValues[0], length: 2 };
    }

    if (numCards === 3 && cardValues[0] === cardValues[1] && cardValues[1] === cardValues[2]) {
        return { valid: true, type: 'triple', value: cardValues[0], length: 3 };
    }

    // Basic Straight (Shunzi) - at least 5 cards, consecutive values (excluding 2 and Jokers)
    if (numCards >= 5 && areConsecutive(cardValues) && !cardValues.includes(15) && !cardValues.includes(16) && !cardValues.includes(17)) {
        return { valid: true, type: 'straight', value: cardValues[0], length: numCards };
    }

    // Basic Bomb (Zhadan) - four cards of the same value
    if (numCards === 4 && cardValues[0] === cardValues[1] && cardValues[1] === cardValues[2] && cardValues[2] === cardValues[3]) {
        return { valid: true, type: 'bomb', value: cardValues[0], length: 4 };
    }


    // TODO: Add validation for other complex types:
    // Three with one (San Dai Yi)
    // Three with pair (San Dai Dui)
    // Consecutive pairs (Lian Dui)
    // Triplet sequences (Fei Ji)
    // Four with two singles (Si Dai Er Dan)
    // Four with two pairs (Si Dai Er Dui)


    return { valid: false, type: null }; // Default to invalid if no type matches
  };

  // Helper function to check if values are consecutive for a straight
  const areConsecutive = (values) => {
      for (let i = 0; i < values.length - 1; i++) {
          if (values[i + 1] !== values[i] + 1) {
              return false;
          }
      }
      return true;
  };

  // Function to check if the current play can beat the last played cards
  const canBeatLastPlay = (current, last) => {
      if (!last || last.type === null) {
          return current.valid; // Can play anything if no last play
      }

      if (!current.valid) {
          return false; // Invalid play cannot beat anything
      }

      // Rocket beats everything except another rocket (not possible in one game)
      if (current.type === 'rocket') return true;
      // Bomb beats any non-bomb/non-rocket play or a lower bomb
      if (current.type === 'bomb' && last.type !== 'bomb' && last.type !== 'rocket') return true;
      if (current.type === 'bomb' && last.type === 'bomb' && current.value > last.value) return true;

      // Same type comparison
      if (current.type === last.type && current.length === last.length && current.value > last.value) {
          return true;
      }
      // TODO: Add specific comparison rules for different types (e.g., straights of different lengths)

      return false; // Cannot beat the last play
  };

  const handleCardClick = (cardFilename) => {
    // Only allow selecting cards if this is the current player
    if (!isCurrentPlayer) {
      return;
    }

    // Toggle selection of the card
    if (selectedCards.includes(cardFilename)) {
      setSelectedCards(selectedCards.filter(card => card !== cardFilename));
    } else {
      setSelectedCards([...selectedCards, cardFilename]);
    }

    // Re-validate the play after selection changes
    const updatedSelectedCards = selectedCards.includes(cardFilename)
        ? selectedCards.filter(card => card !== cardFilename)
        : [...selectedCards, cardFilename];
    setCurrentPlay(isValidPlay(updatedSelectedCards));
    console.log("Selected Cards:", updatedSelectedCards, "Current Play:", isValidPlay(updatedSelectedCards)); // Log selection changes and validation
  };
  
   const handleCancelSelection = () => {
    setSelectedCards([]); // Clear selected cards
    setCurrentPlay({ valid: false, type: null, value: null, length: 0 }); // Reset play validation
  };

  const handlePlayCards = () => {
    // TODO: Implement logic to send the selected cards to the backend
    // You need to pass the last played cards from the GameTable/App state to canBeatLastPlay
    // For now, we'll just log based on currentPlay validity
    // Assuming `lastPlayedCards` is available in this scope (e.g., passed as a prop)
    // if (currentPlay.valid && canBeatLastPlay(currentPlay, lastPlayedCards)) { // Example usage, lastPlayedCards is not yet a prop
    if (currentPlay.valid) { // For now, just check if the play is valid
      console.log("Valid play:", selectedCards);
      // TODO: Send play to backend
      // Clear selected cards after a valid play
    // } else {
    //   console.log("Invalid play or cannot beat last play");
    //   // Provide user feedback
    // }
    console.log("Attempting to play:", selectedCards);
  };

  return (
    <div className={`player-area ${player.isLandlord ? 'landlord' : ''} ${player.isCurrentPlayer ? 'current-player-area' : ''}`}>
      <h3>{player.name} {player.isLandlord && '(地主)'}</h3>
      <p>Score: {player.score}</p>

      <div className="player-hand"> {/* Changed class name to player-hand for consistency with CSS */}
        {player.hand && player.hand.map((cardFilename, index) => (
          // Assuming player.hand is an array of card filenames
          <Card
            key={index}
            filename={cardFilename}
            onClick={() => handleCardClick(cardFilename)} // Add click handler
            isSelected={selectedCards.includes(cardFilename)} // Pass selection status
          />
        ))}
      </div>

      {/* Render player actions only for the current player */}
      {isCurrentPlayer && (
        <div className="action-buttons">
          {/* TODO: Implement logic to show/hide buttons based on game state (e.g., calling landlord phase, playing phase) */}

          {/* Example: Show call landlord buttons in calling phase */}
          {/* {gameState === 'calling_landlord' && (
            <>
              <button>叫地主</button>
              <button>不叫</button>
            </>
          )} */}

          {/* Placeholder buttons */}
          <button disabled={true}>叫地主</button> {/* Disable for now */}
          <button disabled={true}>不叫</button> {/* Disable for now */}
          {/* Disable '出牌' button if no cards are selected or play is invalid */}
          <button onClick={handlePlayCards} disabled={!currentPlay.valid}>出牌</button> {/* Use currentPlay state */}
          <button onClick={() => console.log("Pass")}>不要</button> {/* Add handler for "Pass" */}
          <button onClick={handleCancelSelection}>取消选择</button> {/* Call handleCancelSelection */}
        </div>
      )}
    </div>
  );
}

export default PlayerArea;