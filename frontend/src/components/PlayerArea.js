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

  if (!player) return <div className="player-area-placeholder">空位</div>;

  const handleSelect = (card, source) => {
    setSelected(prev =>
      prev.includes(card)
        ? prev.filter(c => c !== card)
        : [...prev, card]
    );
  };

  const moveSelectedTo = (target) => {
    const targetLimit = target === 'front' ? 3 : 5;
    let targetHand, setTargetHand;

    if (target === 'front') { [targetHand, setTargetHand] = [frontHand, setFrontHand]; }
    if (target === 'middle') { [targetHand, setTargetHand] = [middleHand, setMiddleHand]; }
    if (target === 'back') { [targetHand, setTargetHand] = [backHand, setBackHand]; }

    const cardsToMove = selected.filter(c => unassignedCards.includes(c));
    if (targetHand.length + cardsToMove.length > targetLimit) {
      alert(`此道不能超过 ${targetLimit} 张牌。`);
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
      alert('请将所有牌都分配好再提交。');
      return;
    }
    // Player ID is now handled by the session on the backend
    await submitHand(gameId, frontHand, middleHand, backHand);
  };

  const isReadyToSubmit = frontHand.length === 3 && middleHand.length === 5 && backHand.length === 5;

  if (!isCurrentPlayer) {
    return (
      <div className="player-area opponent">
        <h3>{player.name}</h3>
        <p>分数: {player.score}</p>
        <p>牌数: {player.hand_count || (player.hand ? player.hand.length : 0)}</p>
      </div>
    );
  }

  return (
    <div className="player-area current-player-area">
      <h3>{player.name} (你)</h3>
      <div className="hand-arrangement-ui">
        <HandSlot name="尾道" cards={backHand} onCardClick={(c) => handleSelect(c, 'back')} selectedCards={selected} limit={5} />
        <HandSlot name="中道" cards={middleHand} onCardClick={(c) => handleSelect(c, 'middle')} selectedCards={selected} limit={5} />
        <HandSlot name="头道" cards={frontHand} onCardClick={(c) => handleSelect(c, 'front')} selectedCards={selected} limit={3} />

        <div className="unassigned-cards">
            <h4>我的手牌</h4>
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
            <button onClick={() => moveSelectedTo('front')} disabled={selected.length === 0}>移至头道</button>
            <button onClick={() => moveSelectedTo('middle')} disabled={selected.length === 0}>移至中道</button>
            <button onClick={() => moveSelectedTo('back')} disabled={selected.length === 0}>移至尾道</button>
            <button onClick={returnSelected} disabled={selected.length === 0}>返回手牌</button>
            <button onClick={handleSubmit} disabled={!isReadyToSubmit}>确认理牌</button>
        </div>
      </div>
    </div>
  );
}

export default PlayerArea;
