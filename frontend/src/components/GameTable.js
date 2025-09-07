import React from 'react';
import Card from './Card';

const PlayerShowdownHand = ({ handData }) => {
  if (!handData) return null;

  const { scores, isValid, playerId, back, middle, front } = handData;
  const royalty_details = scores?.royalty_details;

  return (
    <div className={`showdown-player-hand ${!isValid ? 'scooped' : ''}`}>
      <h4>玩家 {playerId.slice(-4)}</h4>
      {!isValid ? (
        <p className="scooped-message">打枪! (-6)</p>
      ) : (
        <>
          <div className="hand-row">
            <span>尾道:</span>
            {back.map(c => <Card key={c} filename={c} />)}
            {royalty_details?.back > 0 && <span className="royalty-badge">+{royalty_details.back}</span>}
          </div>
          <div className="hand-row">
            <span>中道:</span>
            {middle.map(c => <Card key={c} filename={c} />)}
            {royalty_details?.middle > 0 && <span className="royalty-badge">+{royalty_details.middle}</span>}
          </div>
          <div className="hand-row">
            <span>头道:</span>
            {front.map(c => <Card key={c} filename={c} />)}
            {royalty_details?.front > 0 && <span className="royalty-badge">+{royalty_details.front}</span>}
          </div>
        </>
      )}
      {scores ? (
        <div className="score-details">
            <p>比牌得分: {scores.comparison_score > 0 ? `+${scores.comparison_score}` : scores.comparison_score}</p>
            <p>特殊牌型得分: {scores.total_royalty_payout > 0 ? `+${scores.total_royalty_payout}` : scores.total_royalty_payout}</p>
            <p><strong>本局总分: {scores.final_score > 0 ? `+${scores.final_score}` : scores.final_score}</strong></p>
        </div>
      ) : (
          <p>本局总分: {handData.roundScore}</p>
      )}
    </div>
  );
};

function GameTable({ game }) {
  if (!game) {
    return (
      <div className="game-table">
        <p>等待游戏开始...</p>
      </div>
    );
  }

  const renderContent = () => {
    switch (game.state) {
      case 'setting_hands':
        const submittedCount = game.hands.filter(h => h.isSubmitted).length;
        const totalPlayers = game.hands.length;
        return (
          <div>
            <h2>理牌阶段</h2>
            <p>请将你的13张牌分组成头、中、尾三道。</p>
            <p>请确保尾道牌型最大，头道牌型最小。</p>
            <h3>{submittedCount} / {totalPlayers} 位玩家已准备</h3>
          </div>
        );

      case 'showdown':
      case 'finished':
        return (
          <div>
            <h2>比牌</h2>
            <div className="showdown-area">
              {game.hands.map(handData => (
                <PlayerShowdownHand key={handData.playerId} handData={handData} />
              ))}
            </div>
          </div>
        );

      default:
        return <p>游戏状态: {game.state}</p>;
    }
  };

  return (
    <div className="game-table">
      {renderContent()}
    </div>
  );
}

export default GameTable;