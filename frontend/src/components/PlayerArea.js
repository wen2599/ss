import React, { useState, useEffect } from 'react';
import Card from './Card';
import { setHand } from '../api';

function PlayerArea({ player, isCurrentPlayer, roomId, onPlay }) {
  const [unassignedCards, setUnassignedCards] = useState([]);
  const [selectedCards, setSelectedCards] = useState([]);
  const [frontHand, setFrontHand] = useState([]);
  const [middleHand, setMiddleHand] = useState([]);
  const [backHand, setBackHand] = useState([]);

  useEffect(() => {
    if (player.hand) {
      setUnassignedCards(player.hand);
    }
  }, [player.hand]);

  const handleCardClick = (cardFilename, sourceHand, handSetter) => {
    if (!isCurrentPlayer || player.hand_is_set) return;

    if (selectedCards.includes(cardFilename)) {
      setSelectedCards(selectedCards.filter(card => card !== cardFilename));
    } else {
      setSelectedCards([...selectedCards, cardFilename]);
    }
  };

  const assignToHand = (handSetter, currentHand, maxLength) => {
    if (selectedCards.length === 0) return;

    const newHand = [...currentHand, ...selectedCards].slice(0, maxLength);
    const newUnassigned = unassignedCards.filter(c => !selectedCards.includes(c));

    handSetter(newHand);
    setUnassignedCards(newUnassigned);
    setSelectedCards([]);
  };

  const handleConfirmHand = async () => {
    if (frontHand.length !== 3 || middleHand.length !== 5 || backHand.length !== 5) {
      alert("请将13张牌全部分配到前、中、后墩。");
      return;
    }

    try {
      const response = await setHand(roomId, player.id, frontHand, middleHand, backHand);
      if (response.success) {
        onPlay(roomId, player.id); // Refresh game state
      } else {
        alert(`理牌失败: ${response.message}`);
      }
    } catch (error) {
      console.error('理牌失败:', error);
      alert('理牌时发生错误。');
    }
  };

  return (
    <div className={`player-area ${player.isCurrentPlayer ? 'current-player-area' : ''}`}>
      <h3>{player.name}</h3>
      <p>牌数: {player.hand_count}</p>

      {isCurrentPlayer && !player.hand_is_set && (
        <>
          <div className="player-hand">
            {unassignedCards.map((cardFilename) => (
              <Card
                key={cardFilename}
                filename={cardFilename}
                onClick={() => handleCardClick(cardFilename)}
                isSelected={selectedCards.includes(cardFilename)}
              />
            ))}
          </div>
          <div className="hand-arrangement-area">
            <div className="hand-segment">
              <h4>前墩 (3张)</h4>
              <div className="hand-segment-cards">
                {frontHand.map(c => <Card key={c} filename={c} />)}
              </div>
              <button onClick={() => assignToHand(setFrontHand, frontHand, 3)}>设置前墩</button>
            </div>
            <div className="hand-segment">
              <h4>中墩 (5张)</h4>
              <div className="hand-segment-cards">
                {middleHand.map(c => <Card key={c} filename={c} />)}
              </div>
              <button onClick={() => assignToHand(setMiddleHand, middleHand, 5)}>设置中墩</button>
            </div>
            <div className="hand-segment">
              <h4>后墩 (5张)</h4>
              <div className="hand-segment-cards">
                {backHand.map(c => <Card key={c} filename={c} />)}
              </div>
              <button onClick={() => assignToHand(setBackHand, backHand, 5)}>设置后墩</button>
            </div>
            <button onClick={handleConfirmHand}>确认牌型</button>
          </div>
        </>
      )}
      {player.hand_is_set && (
        <div className="final-hands">
            <h4>前墩: {player.front_hand.join(' ')}</h4>
            <h4>中墩: {player.middle_hand.join(' ')}</h4>
            <h4>后墩: {player.back_hand.join(' ')}</h4>
        </div>
      )}
    </div>
  );
}

export default PlayerArea;
