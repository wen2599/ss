import React, { useState } from 'react'; 
import Card from './Card'; 

function PlayerArea({ player, isCurrentPlayer }) {
  const [selectedCards, setSelectedCards] = useState([]);
  const [currentPlay, setCurrentPlay] = useState({ valid: false, type: null, value: null, length: 0 });

  const getCardComparableValue = (filename) => {
    const nameParts = filename.split('_');
    const value = nameParts[0];
    const suit = nameParts[2] ? nameParts[2].split('.')[0] : '';

    const valueMap = {
      '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9, '10': 10,
      'jack': 11, 'queen': 12, 'king': 13, 'ace': 14, '2': 15
    };

    let numericalValue = valueMap[value] || 0;
    if (filename === 'black_joker.svg') numericalValue = 16;
    if (filename === 'red_joker.svg') numericalValue = 17;

    const suitMap = {
      'diamonds': 1, 'clubs': 2, 'hearts': 3, 'spades': 4
    };
    let suitValue = suitMap[suit] || 0;

    return { value: numericalValue, suit: suitValue, filename: filename };
  };

  const compareCards = (cardA, cardB) => {
      if (cardA.value !== cardB.value) return cardA.value - cardB.value;
      return cardA.suit - cardB.suit;
  };

  const isValidPlay = (cards) => {
    const numCards = cards.length;
    if (numCards === 0) return { valid: false, type: null };

    const comparableCards = cards.map(getCardComparableValue).sort(compareCards);
    const cardValues = comparableCards.map(card => card.value);

    if (numCards === 2 && cardValues.includes(16) && cardValues.includes(17)) {
         return { valid: true, type: 'rocket', value: 18, length: 2 };
    }

    if (numCards === 1) return { valid: true, type: 'single', value: cardValues[0], length: 1 };
    
    if (numCards === 2 && cardValues[0] === cardValues[1]) {
      return { valid: true, type: 'pair', value: cardValues[0], length: 2 };
    }

    if (numCards === 3 && cardValues[0] === cardValues[1] && cardValues[1] === cardValues[2]) {
        return { valid: true, type: 'triple', value: cardValues[0], length: 3 };
    }

    if (numCards >= 5 && areConsecutive(cardValues) && !cardValues.includes(15) && !cardValues.includes(16) && !cardValues.includes(17)) {
        return { valid: true, type: 'straight', value: cardValues[0], length: numCards };
    }

    if (numCards === 4 && cardValues[0] === cardValues[1] && cardValues[1] === cardValues[2] && cardValues[2] === cardValues[3]) {
        return { valid: true, type: 'bomb', value: cardValues[0], length: 4 };
    }

    return { valid: false, type: null };
  };

  const areConsecutive = (values) => {
      for (let i = 0; i < values.length - 1; i++) {
          if (values[i + 1] !== values[i] + 1) return false;
      }
      return true;
  };

  const handleCardClick = (cardFilename) => {
    if (!isCurrentPlayer) return;

    if (selectedCards.includes(cardFilename)) {
      setSelectedCards(selectedCards.filter(card => card !== cardFilename));
    } else {
      setSelectedCards([...selectedCards, cardFilename]);
    }

    const updatedSelectedCards = selectedCards.includes(cardFilename)
        ? selectedCards.filter(card => card !== cardFilename)
        : [...selectedCards, cardFilename];
    setCurrentPlay(isValidPlay(updatedSelectedCards));
  };
  
  const handleCancelSelection = () => {
    setSelectedCards([]);
    setCurrentPlay({ valid: false, type: null, value: null, length: 0 });
  };

  const handlePlayCards = () => {
    if (currentPlay.valid) {
      console.log("Valid play:", selectedCards);
      // TODO: Send play to backend
    }
    console.log("Attempting to play:", selectedCards);
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
          <button onClick={handlePlayCards} disabled={!currentPlay.valid}>出牌</button>
          <button onClick={() => console.log("Pass")}>不要</button>
          <button onClick={handleCancelSelection}>取消选择</button>
        </div>
      )}
    </div>
  );
}

export default PlayerArea;
