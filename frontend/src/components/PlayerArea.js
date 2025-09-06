import React, { useState, useEffect } from 'react';
import Card from './Card';
import { submitHand } from '../api';

const HandSlot = ({ name, cards, onCardClick, selectedCards, limit }) => (
  <div className="hand-slot">
    <h4>{name} ({cards.length}/{limit})</h4>
    <div className="card-container">
      {cards.map(card => (
        <Card
          key={card}
          filename={card}
          onClick={() => onCardClick(card)}
          isSelected={selectedCards.includes(card)}
        />
      ))}
    </div>
  </div>
);

function PlayerArea({ player, isCurrentPlayer, gameId, roomId }) {
  const [unassignedCards, setUnassignedCards] = useState([]);
  const [frontHand, setFrontHand] = useState([]);
  const [middleHand, setMiddleHand] = useState([]);
  const [backHand, setBackHand] = useState([]);
  const [selected, setSelected] = useState([]);

  useEffect(() => {
    if (isCurrentPlayer && player.hand) {
      setUnassignedCards(player.hand);
      setFrontHand([]);
      setMiddleHand([]);
      setBackHand([]);
    }
  }, [isCurrentPlayer, player.hand]);

  if (!player) return <div className="player-area-placeholder">Empty Seat</div>;

  const handleSelect = (card, source) => {
    setSelected(prev =>
      prev.includes(card)
        ? prev.filter(c => c !== card)
        : [...prev, card]
    );
  };

  const moveSelectedTo = (target) => {
    const targetLimit = target === 'front' ? 3 : 5;
    let targetHand, setTargetHand, sourceHand, setSourceHand;

    // This logic is simplified. A full implementation would need to know the source of each selected card.
    // For now, let's assume we are always moving from unassigned.
    if (target === 'front') { [targetHand, setTargetHand] = [frontHand, setFrontHand]; }
    if (target === 'middle') { [targetHand, setTargetHand] = [middleHand, setMiddleHand]; }
    if (target === 'back') { [targetHand, setTargetHand] = [backHand, setBackHand]; }

    const cardsToMove = selected.filter(c => unassignedCards.includes(c));
    if (targetHand.length + cardsToMove.length > targetLimit) {
      alert(`Hand cannot exceed ${targetLimit} cards.`);
      return;
    }

    setTargetHand([...targetHand, ...cardsToMove]);
    setUnassignedCards(unassignedCards.filter(c => !cardsToMove.includes(c)));
    setSelected([]);
  };

  const returnSelected = () => {
      const fromFront = selected.filter(c => frontHand.includes(c));
      const fromMiddle = selected.filter(c => middleHand.includes(c));
      const fromBack = selected.filter(c => backHand.includes(c));

      setFrontHand(frontHand.filter(c => !fromFront.includes(c)));
      setMiddleHand(middleHand.filter(c => !fromMiddle.includes(c)));
      setBackHand(backHand.filter(c => !fromBack.includes(c)));

      setUnassignedCards([...unassignedCards, ...fromFront, ...fromMiddle, ...fromBack]);
      setSelected([]);
  };

  const handleSubmit = async () => {
    if (frontHand.length !== 3 || middleHand.length !== 5 || backHand.length !== 5) {
      alert('All hands must be full to submit.');
      return;
    }
    await submitHand(gameId, player.id, frontHand, middleHand, backHand);
    // After submit, the App's polling will update the view.
  };

  const isReadyToSubmit = frontHand.length === 3 && middleHand.length === 5 && backHand.length === 5;

  if (!isCurrentPlayer) {
    return (
      <div className="player-area opponent">
        <h3>{player.name}</h3>
        <p>Score: {player.score}</p>
        <p>Cards: {player.hand_count || (player.hand ? player.hand.length : 0)}</p>
      </div>
    );
  }

  return (
    <div className="player-area current-player-area">
      <h3>{player.name} (You)</h3>
      <div className="hand-arrangement-ui">
        <HandSlot name="Back Hand" cards={backHand} onCardClick={(c) => handleSelect(c, 'back')} selectedCards={selected} limit={5} />
        <HandSlot name="Middle Hand" cards={middleHand} onCardClick={(c) => handleSelect(c, 'middle')} selectedCards={selected} limit={5} />
        <HandSlot name="Front Hand" cards={frontHand} onCardClick={(c) => handleSelect(c, 'front')} selectedCards={selected} limit={3} />

        <div className="unassigned-cards">
            <h4>Your Hand</h4>
            <div className="card-container">
            {unassignedCards.map(card => (
                <Card
                key={card}
                filename={card}
                onClick={() => handleSelect(card, 'unassigned')}
                isSelected={selected.includes(card)}
                />
            ))}
            </div>
        </div>

        <div className="action-buttons">
            <button onClick={() => moveSelectedTo('front')} disabled={selected.length === 0}>To Front</button>
            <button onClick={() => moveSelectedTo('middle')} disabled={selected.length === 0}>To Middle</button>
            <button onClick={() => moveSelectedTo('back')} disabled={selected.length === 0}>To Back</button>
            <button onClick={returnSelected} disabled={selected.length === 0}>Return to Hand</button>
            <button onClick={handleSubmit} disabled={!isReadyToSubmit}>Confirm Hand</button>
        </div>
      </div>
    </div>
  );
}

export default PlayerArea;
