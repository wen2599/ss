import React, { useState } from 'react';
import Card from './Card';
import { useGame } from '../contexts/GameContext';

const PlayerArea = ({ player, isCurrentPlayer, gameId }) => {
    const { submitHand, loading, error } = useGame();
    const [selectedCards, setSelectedCards] = useState([]);
    const [front, setFront] = useState([]);
    const [middle, setMiddle] = useState([]);
    const [back, setBack] = useState([]);

    const handleCardClick = (card) => {
        setSelectedCards(prev =>
            prev.includes(card) ? prev.filter(c => c !== card) : [...prev, card]
        );
    };

    const assignToHand = (handSetter, numCards) => {
        if (selectedCards.length === numCards) {
            handSetter(selectedCards);
            // remove selected cards from player's hand
            player.hand = player.hand.filter(c => !selectedCards.includes(c));
            setSelectedCards([]);
        }
    };

    const canSubmit = front.length === 3 && middle.length === 5 && back.length === 5;

    const handleSubmit = () => {
        if (canSubmit) {
            submitHand(gameId, front, middle, back);
        }
    };

    return (
        <div className={`player-area ${isCurrentPlayer ? 'current-player' : ''}`}>
            <div className="player-info">
                <p>{player.name}</p>
                <p>Score: {player.score}</p>
            </div>
            <div className="player-hand">
                {player.hand && player.hand.map(card => (
                    <Card
                        key={card}
                        card={card}
                        isSelected={selectedCards.includes(card)}
                        onClick={() => handleCardClick(card)}
                    />
                ))}
            </div>
            {isCurrentPlayer && (
                <div className="hand-actions">
                    <button onClick={() => assignToHand(setFront, 3)} disabled={selectedCards.length !== 3}>Set Front (3)</button>
                    <button onClick={() => assignToHand(setMiddle, 5)} disabled={selectedCards.length !== 5}>Set Middle (5)</button>
                    <button onClick={() => assignToHand(setBack, 5)} disabled={selectedCards.length !== 5}>Set Back (5)</button>
                    <button onClick={handleSubmit} disabled={!canSubmit || loading}>Submit Hand</button>
                    {error && <p className="error-message">{error}</p>}
                </div>
            )}
            <div className="arranged-hands">
                <div className="hand-segment">
                    <p>Front:</p>
                    {front.map(card => <Card key={card} card={card} />)}
                </div>
                <div className="hand-segment">
                    <p>Middle:</p>
                    {middle.map(card => <Card key={card} card={card} />)}
                </div>
                <div className="hand-segment">
                    <p>Back:</p>
                    {back.map(card => <Card key={card} card={card} />)}
                </div>
            </div>
        </div>
    );
};

export default PlayerArea;
