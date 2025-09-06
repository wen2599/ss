import React, { useState, useEffect } from 'react';
import Card from './Card';
// We'll need a new API function to submit the hands
// import { submitHands } from '../api';

function PlayerArea({ player, isCurrentPlayer, gameId, onArrangementConfirmed }) {
  // The player's full 13-card hand, which does not change
  const [fullHand, setFullHand] = useState(player.hand || []);

  // State for the three hands being arranged
  const [frontHand, setFrontHand] = useState([]);
  const [middleHand, setMiddleHand] = useState([]);
  const [backHand, setBackHand] = useState([]);

  // The hand currently being targeted for adding cards
  const [targetHand, setTargetHand] = useState('front'); // 'front', 'middle', or 'back'

  // Cards that are not yet placed in one of the three hands
  const [unassignedCards, setUnassignedCards] = useState(fullHand);

  useEffect(() => {
    // Update unassigned cards when the three hands change
    const assignedCards = [...frontHand, ...middleHand, ...backHand];
    setUnassignedCards(fullHand.filter(card => !assignedCards.includes(card)));
  }, [frontHand, middleHand, backHand, fullHand]);

  const handleCardClick = (card) => {
    if (!isCurrentPlayer) return;

    // Check if the card is already assigned
    const isAssigned = frontHand.includes(card) || middleHand.includes(card) || backHand.includes(card);

    if (isAssigned) {
        // If assigned, remove it from its current hand (making it unassigned)
        setFrontHand(frontHand.filter(c => c !== card));
        setMiddleHand(middleHand.filter(c => c !== card));
        setBackHand(backHand.filter(c => c !== card));
    } else {
        // If unassigned, add it to the target hand
        if (targetHand === 'front' && frontHand.length < 3) {
            setFrontHand([...frontHand, card]);
        } else if (targetHand === 'middle' && middleHand.length < 5) {
            setMiddleHand([...middleHand, card]);
        } else if (targetHand === 'back' && backHand.length < 5) {
            setBackHand([...backHand, card]);
        } else {
            alert(`The ${targetHand} hand is full!`);
        }
    }
  };
  
  const handleConfirmHands = async () => {
    if (frontHand.length !== 3 || middleHand.length !== 5 || backHand.length !== 5) {
      alert('请将13张牌全部分配到前、中、后三墩');
      return;
    }

    console.log("Confirming hands:", { front: frontHand, middle: middleHand, back: backHand });
    // TODO: Call the actual API to submit hands
    // await submitHands(gameId, player.id, { front: frontHand, middle: middleHand, back: backHand });

    // Notify parent component that arrangement is done
    if (onArrangementConfirmed) {
      onArrangementConfirmed(player.id);
    }
  };

  const renderHand = (cards, handName, limit) => (
    <div className={`hand-zone ${targetHand === handName ? 'target' : ''}`} onClick={() => setTargetHand(handName)}>
      <h4>{handName.toUpperCase()} ({cards.length} / {limit})</h4>
      <div className="player-hand">
        {cards.map(card => <Card key={card} filename={card} onClick={() => handleCardClick(card)} />)}
      </div>
    </div>
  );

  // If not the current player, show a simplified view
  if (!isCurrentPlayer) {
    // In a real game, we would show the back of the cards or the confirmed hands
    return (
      <div className="player-area opponent">
        <h3>{player.name}</h3>
        <p>正在理牌...</p>
        {/* Or show confirmed hands if game state is 'comparing' */}
      </div>
    );
  }

  return (
    <div className="player-area current-player-area">
      <h3>{player.name} (你)</h3>

      <div className="unassigned-cards-area">
        <h4>你的手牌 (点击牌来分配)</h4>
        <div className="player-hand">
          {unassignedCards.map(card => <Card key={card} filename={card} onClick={() => handleCardClick(card)} />)}
        </div>
      </div>

      <div className="arrangement-area">
        {renderHand(frontHand, 'front', 3)}
        {renderHand(middleHand, 'middle', 5)}
        {renderHand(backHand, 'back', 5)}
      </div>

      <div className="action-buttons">
        <button onClick={handleConfirmHands}>确定</button>
      </div>
    </div>
  );
}

export default PlayerArea;
